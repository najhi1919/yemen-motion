<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'display_name',
    'professional_title',
    'primary_specialty',
    'bio',
    'avatar_path',
    'cover_path',
    'cover_focal_x',
    'cover_focal_y',
    'availability',
    'years_of_experience',
    'professional_note',
    'show_availability_publicly',
    'show_specialties_publicly',
    'show_skills_publicly',
    'show_tools_publicly',
    'show_languages_publicly',
    'show_experience_publicly',
])]
class DesignerProfile extends Model
{
    use HasFactory;

    public const AVAILABILITY_AVAILABLE = 'available';

    public const AVAILABILITY_PARTIALLY_AVAILABLE = 'partially_available';

    public const AVAILABILITY_UNAVAILABLE = 'unavailable';

    public const PUBLICATION_DRAFT = 'draft';

    public const PUBLICATION_PUBLISHED = 'published';

    public const PUBLICATION_HIDDEN = 'hidden';

    public const PUBLICATION_STATUSES = [
        self::PUBLICATION_DRAFT,
        self::PUBLICATION_PUBLISHED,
        self::PUBLICATION_HIDDEN,
    ];

    public const AVAILABILITIES = [
        self::AVAILABILITY_AVAILABLE,
        self::AVAILABILITY_PARTIALLY_AVAILABLE,
        self::AVAILABILITY_UNAVAILABLE,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function specialties(): HasMany
    {
        return $this->hasMany(DesignerProfileSpecialty::class)->orderBy('sort_order')->orderBy('id');
    }

    public function skills(): HasMany
    {
        return $this->hasMany(DesignerProfileSkill::class)->orderBy('sort_order')->orderBy('id');
    }

    public function tools(): HasMany
    {
        return $this->hasMany(DesignerProfileTool::class)->orderBy('sort_order')->orderBy('id');
    }

    public function languages(): HasMany
    {
        return $this->hasMany(DesignerProfileLanguage::class)->orderBy('sort_order')->orderBy('id');
    }

    protected function casts(): array
    {
        return [
            'cover_focal_x' => 'integer',
            'cover_focal_y' => 'integer',
            'published_at' => 'datetime',
            'hidden_at' => 'datetime',
            'years_of_experience' => 'integer',
            'show_availability_publicly' => 'boolean',
            'show_specialties_publicly' => 'boolean',
            'show_skills_publicly' => 'boolean',
            'show_tools_publicly' => 'boolean',
            'show_languages_publicly' => 'boolean',
            'show_experience_publicly' => 'boolean',
        ];
    }
}
