<?php

namespace App\Models;

use Database\Factories\WorkCategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkCategory extends Model
{
    /** @use HasFactory<WorkCategoryFactory> */
    use HasFactory;

    protected $fillable = [
        'name_ar',
        'name_en',
        'slug',
        'disabled_at',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'disabled_at' => 'datetime',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return HasMany<Work, $this>
     */
    public function works(): HasMany
    {
        return $this->hasMany(Work::class, 'category_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('disabled_at');
    }

    public function scopeDisabled(Builder $query): Builder
    {
        return $query->whereNotNull('disabled_at');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function isActive(): bool
    {
        return $this->disabled_at === null;
    }
}
