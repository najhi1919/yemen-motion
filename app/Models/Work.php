<?php

namespace App\Models;

use Database\Factories\WorkFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public const VISIBILITY_HIDDEN = 'hidden';

    public const VISIBILITY_PUBLIC = 'public';

    protected $guarded = ['id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'designer_id' => 'integer',
            'reviewer_id' => 'integer',
            'category_id' => 'integer',
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
}
