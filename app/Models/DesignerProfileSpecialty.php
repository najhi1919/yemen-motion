<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DesignerProfileSpecialty extends Model
{
    public const KIND_SERVICE = 'service';
    public const KIND_OCCASION = 'occasion';
    public const KIND_STYLE = 'style';
    public const KINDS = [self::KIND_SERVICE, self::KIND_OCCASION, self::KIND_STYLE];

    protected $fillable = ['kind', 'name', 'normalized_name', 'sort_order'];
    protected $guarded = ['id', 'designer_profile_id'];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(DesignerProfile::class, 'designer_profile_id');
    }
}
