<?php

namespace App\Models;

use Database\Factories\WorkFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Work extends Model
{
    /** @use HasFactory<WorkFactory> */
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_IN_REVIEW = 'in_review';

    public const STATUS_CHANGES_REQUESTED = 'changes_requested';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_HIDDEN = 'hidden';

    public const STATUS_ARCHIVED = 'archived';

    public const DESIGNER_ARCHIVABLE_STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_CHANGES_REQUESTED,
        self::STATUS_PUBLISHED,
        self::STATUS_REJECTED,
        self::STATUS_HIDDEN,
    ];

    public const DESIGNER_ARCHIVE_BLOCKED_STATUSES = [
        self::STATUS_SUBMITTED,
        self::STATUS_IN_REVIEW,
        self::STATUS_APPROVED,
    ];

    public const VISIBILITY_HIDDEN = 'hidden';

    public const VISIBILITY_PUBLIC = 'public';

    public const MEDIA_TYPE_IMAGE = 'image';

    public const MEDIA_TYPE_VIDEO = 'video';

    public const MEDIA_TYPE_GALLERY = 'gallery';

    public const MEDIA_TYPES = [
        self::MEDIA_TYPE_IMAGE,
        self::MEDIA_TYPE_VIDEO,
        self::MEDIA_TYPE_GALLERY,
    ];

    public const COVER_DISPLAY_MODE_FILL = 'fill';

    public const COVER_DISPLAY_MODE_FIT = 'fit';

    public const COVER_DISPLAY_MODES = [
        self::COVER_DISPLAY_MODE_FILL,
        self::COVER_DISPLAY_MODE_FIT,
    ];

    private const PUBLIC_CODE_ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    private const PUBLIC_CODE_ATTEMPTS = 20;

    protected $guarded = ['id', 'public_code'];

    protected static function booted(): void
    {
        static::creating(function (Work $work): void {
            if ($work->getAttribute('public_code')) {
                return;
            }

            $work->setAttribute('public_code', self::generateUniquePublicCode());
        });

        static::updating(function (Work $work): void {
            if ($work->isDirty('public_code') && $work->exists) {
                $work->setAttribute('public_code', $work->getOriginal('public_code'));
            }
        });

        static::updated(function (Work $work): void {
            $ownershipChanged = $work->wasChanged('designer_id');
            $lostPublicEligibility = $work->wasChanged([
                'status',
                'visibility_status',
            ]) && (
                $work->status !== self::STATUS_PUBLISHED
                || $work->visibility_status !== self::VISIBILITY_PUBLIC
            );

            if (! $ownershipChanged && ! $lostPublicEligibility) {
                return;
            }

            $profileIds = $work->featuredProfileSelections()
                ->pluck('designer_profile_id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->all();

            if ($profileIds === []) {
                return;
            }

            $work->featuredProfileSelections()->delete();

            DesignerProfile::query()
                ->whereKey($profileIds)
                ->update(['updated_at' => now()]);
        });
    }

    private static function generateUniquePublicCode(): string
    {
        $maximum = strlen(self::PUBLIC_CODE_ALPHABET) - 1;

        for ($attempt = 0; $attempt < self::PUBLIC_CODE_ATTEMPTS; $attempt++) {
            $suffix = '';

            for ($index = 0; $index < 10; $index++) {
                $suffix .= self::PUBLIC_CODE_ALPHABET[random_int(0, $maximum)];
            }

            $code = 'YM-W-'.$suffix;

            if (! self::query()->where('public_code', $code)->exists()) {
                return $code;
            }
        }

        throw new \RuntimeException('Unable to generate a unique work code.');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'designer_id' => 'integer',
            'reviewer_id' => 'integer',
            'category_id' => 'integer',
            'cover_media_id' => 'integer',
            'cover_focal_x' => 'integer',
            'cover_focal_y' => 'integer',
            'price_amount' => 'decimal:2',
            'delivery_days' => 'integer',
            'is_featured' => 'boolean',
            'is_pinned' => 'boolean',
            'is_trusted_direct_publish' => 'boolean',
            'views_count' => 'integer',
            'likes_count' => 'integer',
            'reports_count' => 'integer',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'approved_at' => 'datetime',
            'published_at' => 'datetime',
            'rejected_at' => 'datetime',
            'hidden_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function designer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'designer_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    /**
     * @return BelongsTo<WorkCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(WorkCategory::class, 'category_id');
    }

    /**
     * @return BelongsToMany<WorkTag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(
            WorkTag::class,
            'work_tag_assignments',
            'work_id',
            'work_tag_id',
        );
    }

    /**
     * @return HasMany<DesignerProfileFeaturedWork, $this>
     */
    public function featuredProfileSelections(): HasMany
    {
        return $this->hasMany(
            DesignerProfileFeaturedWork::class,
            'work_id',
        );
    }

    /**
     * @return HasMany<WorkReport, $this>
     */
    public function reports(): HasMany
    {
        return $this->hasMany(WorkReport::class);
    }

    /**
     * @return HasMany<WorkMedia, $this>
     */
    public function media(): HasMany
    {
        return $this->hasMany(WorkMedia::class)
            ->orderBy('position')
            ->orderBy('id');
    }

    /**
     * @return BelongsTo<WorkMedia, $this>
     */
    public function coverMedia(): BelongsTo
    {
        return $this->belongsTo(WorkMedia::class, 'cover_media_id');
    }

    public function scopeSubmitted(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_SUBMITTED);
    }

    public function scopeInReview(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_IN_REVIEW);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query
            ->where('status', self::STATUS_PUBLISHED)
            ->where('visibility_status', self::VISIBILITY_PUBLIC);
    }

    public function scopeHidden(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_HIDDEN);
    }

    public function scopeArchived(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ARCHIVED);
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    public function scopePinned(Builder $query): Builder
    {
        return $query->where('is_pinned', true);
    }

    public function scopeReported(Builder $query): Builder
    {
        return $query->where('reports_count', '>', 0);
    }

    public function canBeArchivedByDesigner(): bool
    {
        return in_array($this->status, self::DESIGNER_ARCHIVABLE_STATUSES, true);
    }

    public function canBeRestoredByDesigner(): bool
    {
        return $this->status === self::STATUS_ARCHIVED;
    }

    /**
     * @return array{status: string, visibility_status: string}
     */
    public function designerRestoreTarget(): array
    {
        if ($this->archived_from_status === self::STATUS_PUBLISHED) {
            return [
                'status' => self::STATUS_PUBLISHED,
                'visibility_status' => in_array(
                    $this->archived_from_visibility_status,
                    [self::VISIBILITY_PUBLIC, self::VISIBILITY_HIDDEN],
                    true,
                ) ? $this->archived_from_visibility_status : self::VISIBILITY_PUBLIC,
            ];
        }

        if ($this->archived_from_status === self::STATUS_HIDDEN) {
            return ['status' => self::STATUS_HIDDEN, 'visibility_status' => self::VISIBILITY_HIDDEN];
        }

        return ['status' => self::STATUS_DRAFT, 'visibility_status' => self::VISIBILITY_HIDDEN];
    }
}
