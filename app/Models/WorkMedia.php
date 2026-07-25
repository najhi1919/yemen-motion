<?php

namespace App\Models;

use Database\Factories\WorkMediaFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkMedia extends Model
{
    /** @use HasFactory<WorkMediaFactory> */
    use HasFactory, SoftDeletes;

    public const KIND_IMAGE = 'image';

    public const KIND_VIDEO = 'video';

    public const KINDS = [
        self::KIND_IMAGE,
        self::KIND_VIDEO,
    ];

    public const PROCESSING_PENDING = 'pending';

    public const PROCESSING_READY = 'ready';

    public const PROCESSING_FAILED = 'failed';

    public const STAGE_QUEUED = 'queued';

    public const STAGE_VALIDATING = 'validating';

    public const STAGE_PROBING = 'probing';

    public const STAGE_EXTRACTING_METADATA = 'extracting_metadata';

    public const STAGE_GENERATING_POSTER = 'generating_poster';

    public const STAGE_FINALIZING = 'finalizing';

    public const STAGE_READY = 'ready';

    public const STAGE_FAILED = 'failed';

    public const PROCESSING_STAGES = [
        self::STAGE_QUEUED,
        self::STAGE_VALIDATING,
        self::STAGE_PROBING,
        self::STAGE_EXTRACTING_METADATA,
        self::STAGE_GENERATING_POSTER,
        self::STAGE_FINALIZING,
        self::STAGE_READY,
        self::STAGE_FAILED,
    ];

    public const PROCESSING_STATUSES = [
        self::PROCESSING_PENDING,
        self::PROCESSING_READY,
        self::PROCESSING_FAILED,
    ];

    protected $table = 'work_media';

    protected $guarded = ['id'];

    protected $hidden = [
        'disk',
        'path',
        'poster_path',
        'processing_error',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'work_id' => 'integer',
            'uploaded_by' => 'integer',
            'size_bytes' => 'integer',
            'position' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'duration_ms' => 'integer',
            'processing_progress' => 'integer',
            'processing_attempts' => 'integer',
            'processing_started_at' => 'datetime',
            'processing_completed_at' => 'datetime',
            'deleted_at' => 'datetime',
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
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('position')->orderBy('id');
    }
}
