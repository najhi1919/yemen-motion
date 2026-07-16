<?php

namespace App\Models;

use Database\Factories\WorkReportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkReport extends Model
{
    /** @use HasFactory<WorkReportFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_UNDER_REVIEW = 'under_review';

    public const STATUS_DISMISSED = 'dismissed';

    public const STATUS_ARCHIVED = 'archived';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_UNDER_REVIEW,
        self::STATUS_DISMISSED,
        self::STATUS_ARCHIVED,
    ];

    protected $fillable = [
        'work_id',
        'reporter_id',
        'reason_code',
        'details',
        'status',
        'reviewed_by',
        'reviewed_at',
        'dismissed_at',
        'archived_at',
        'resolution_notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
            'dismissed_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Work, $this>
     */
    public function work(): BelongsTo
    {
        return $this->belongsTo(Work::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
