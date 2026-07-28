<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
])]
class DesignerProfile extends Model
{
    use HasFactory;

    public const AVAILABILITY_AVAILABLE = 'available';

    public const AVAILABILITY_PARTIALLY_AVAILABLE = 'partially_available';

    public const AVAILABILITY_UNAVAILABLE = 'unavailable';

    public const PUBLICATION_DRAFT = 'draft';

    public const AVAILABILITIES = [
        self::AVAILABILITY_AVAILABLE,
        self::AVAILABILITY_PARTIALLY_AVAILABLE,
        self::AVAILABILITY_UNAVAILABLE,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'cover_focal_x' => 'integer',
            'cover_focal_y' => 'integer',
            'published_at' => 'datetime',
        ];
    }
}
