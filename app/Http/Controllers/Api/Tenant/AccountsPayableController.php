<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\AccountPayableResource;
use App\Models\AccountPayable;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AccountsPayableController extends Controller
{
    public function index(): JsonResponse
    {
        $query = AccountPayable::with('supplier');

        if ($status = request('status')) {
            $query->where('status', $status);
        }

        if ($supplierId = request('supplier_id')) {
            $query->where('supplier_id', $supplierId);
        }

        if (request()->boolean('overdue', false)) {
            $query->where('status', '!=', 'paid')
                  ->where('status', '!=', 'cancelled')
                  ->where('due_date', '<', now()->toDateString());
        }

        $paginator = $query->orderBy('due_date')->cursorPaginate($this->perPage());

        return ApiResponse::cursor(
            'Accounts payable retrieved successfully.',
            $paginator,
            AccountPayableResource::collection($paginator->getCollection())->resolve(),
        );
    }

    public function show(string $tenant, AccountPayable $accountPayable): JsonResponse
    {
        $accountPayable->load(['supplier', 'purchaseReception.items.product']);

        return ApiResponse::success(
            'Account payable retrieved successfully.',
            AccountPayableResource::make($accountPayable)->resolve(),
        );
    }

    /**
     * Registrar pago parcial o total.
     */
    public function pay(string $tenant, AccountPayable $accountPayable): JsonResponse
    {
        if (in_array($accountPayable->status, ['paid', 'cancelled'])) {
            return ApiResponse::error('This account is already ' . $accountPayable->status . '.', 422);
        }

        $data = request()->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'notes'  => ['nullable', 'string'],
        ]);

        $remaining = $accountPayable->remainingAmount();

        if ((float) $data['amount'] > $remaining) {
            return ApiResponse::error("Payment amount exceeds remaining balance ({$remaining}).", 422);
        }

        return DB::transaction(function () use ($accountPayable, $data): JsonResponse {
            $newPaid = (float) $accountPayable->paid_amount + (float) $data['amount'];
            $isPaid  = $newPaid >= (float) $accountPayable->amount;

            $accountPayable->update([
                'paid_amount' => $newPaid,
                'status'      => $isPaid ? 'paid' : 'partial',
                'paid_at'     => $isPaid ? now() : $accountPayable->paid_at,
                'notes'       => $data['notes'] ?? $accountPayable->notes,
            ]);

            return ApiResponse::success(
                $isPaid ? 'Account fully paid.' : 'Partial payment registered.',
                AccountPayableResource::make($accountPayable->fresh())->resolve(),
            );
        });
    }

    /**
     * Cancelar cuenta por pagar.
     */
    public function cancel(string $tenant, AccountPayable $accountPayable): JsonResponse
    {
        if ($accountPayable->status === 'paid') {
            return ApiResponse::error('Cannot cancel a fully paid account.', 422);
        }

        $accountPayable->update(['status' => 'cancelled']);

        return ApiResponse::success('Account payable cancelled.', AccountPayableResource::make($accountPayable)->resolve());
    }

    private function perPage(): int
    {
        return max(1, min((int) request('per_page', 20), 100));
    }
}
