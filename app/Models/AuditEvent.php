<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditEvent extends Model
{
    /**
     * الحقول المباشرة المسموح للخدمة المركزية بحفظها.
     *
     * @var list<string>
     */
    protected $fillable = [
        'event_type',
        'category',
        'severity',
        'actor_type',
        'actor_id',
        'actor_role',
        'target_type',
        'target_id',
        'action',
        'outcome',
        'ip_address',
        'user_agent',
        'request_id',
        'correlation_id',
        'metadata',
        'occurred_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }
}
