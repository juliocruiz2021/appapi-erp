<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable = [
        'request_id',
        'event',
        'tenant_id',
        'actor_id',
        'actor_email',
        'auditable_type',
        'auditable_id',
        'method',
        'path',
        'ip_address',
        'user_agent',
        'status_code',
        'old_values',
        'new_values',
        'meta',
        'occurred_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'meta' => 'array',
            'occurred_at' => 'datetime',
        ];
    }
}
