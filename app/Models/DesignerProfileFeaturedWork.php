<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DesignerProfileFeaturedWork extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'designer_profile_id' => 'integer',
            'work_id' => 'integer',
            'position' => 'integer',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(DesignerProfile::class, 'designer_profile_id');
    }

    public function work(): BelongsTo
    {
        return $this->belongsTo(Work::class);
    }
}
