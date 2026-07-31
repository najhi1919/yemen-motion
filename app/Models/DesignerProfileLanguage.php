<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DesignerProfileLanguage extends Model
{
    public const LEVEL_BASIC = 'basic';
    public const LEVEL_CONVERSATIONAL = 'conversational';
    public const LEVEL_PROFESSIONAL = 'professional';
    public const LEVEL_NATIVE = 'native';
    public const LEVELS = [self::LEVEL_BASIC, self::LEVEL_CONVERSATIONAL, self::LEVEL_PROFESSIONAL, self::LEVEL_NATIVE];

    protected $fillable = ['name', 'normalized_name', 'level', 'sort_order'];
    protected $guarded = ['id', 'designer_profile_id'];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(DesignerProfile::class, 'designer_profile_id');
    }
}
