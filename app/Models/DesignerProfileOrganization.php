<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DesignerProfileOrganization extends Model
{
    use HasFactory;

    protected $dateFormat = 'Y-m-d H:i:s.u';
    protected $fillable = [
        'designer_profile_id',
        'organization_name',
        'organization_type',
        'description',
        'website_url',
        'logo_path',
        'show_publicly',
    ];

    protected function casts(): array
    {
        return [
            'show_publicly' => 'boolean',
        ];
    }

    public function designerProfile(): BelongsTo
    {
        return $this->belongsTo(DesignerProfile::class);
    }
}
