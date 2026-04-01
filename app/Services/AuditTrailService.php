<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

class AuditTrailService
{
    private const REDACTED = '[REDACTED]';

    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'token',
        'access_token',
        'refresh_token',
        'authorization',
    ];

    /**
     * @param  array<string, mixed>  $context
     */
    public function record(string $event, array $context = []): void
    {
        try {
            $request = request();
            $actor = $context['actor']
                ?? ($request?->bearerToken() !== null ? Auth::guard('api')->user() : null);
            $auditable = $context['auditable'] ?? null;

            AuditLog::query()->create([
                'request_id' => $request?->attributes->get('request_id'),
                'event' => $event,
                'tenant_id' => tenant('id'),
                'actor_id' => $actor instanceof Authenticatable ? $actor->getAuthIdentifier() : null,
                'actor_email' => data_get($actor, 'email', $context['actor_email'] ?? null),
                'auditable_type' => $auditable instanceof Model ? $auditable::class : ($context['auditable_type'] ?? null),
                'auditable_id' => $auditable instanceof Model ? (string) $auditable->getKey() : ($context['auditable_id'] ?? null),
                'method' => $request?->method(),
                'path' => $request?->path(),
                'ip_address' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
                'status_code' => $context['status_code'] ?? null,
                'old_values' => $this->sanitize($context['old_values'] ?? null),
                'new_values' => $this->sanitize($context['new_values'] ?? null),
                'meta' => $this->sanitize($context['meta'] ?? null),
                'occurred_at' => now(),
            ]);
        } catch (Throwable $throwable) {
            Log::warning('Unable to persist audit trail entry.', [
                'event' => $event,
                'error' => $throwable->getMessage(),
            ]);
        }
    }

    private function sanitize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $item) {
            if (is_string($key) && in_array(strtolower($key), self::SENSITIVE_KEYS, true)) {
                $value[$key] = self::REDACTED;

                continue;
            }

            $value[$key] = $this->sanitize($item);
        }

        return $value;
    }
}
