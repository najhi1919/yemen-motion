<?php

namespace App\Http\Requests\Designer;

use App\Models\Work;
use App\Models\WorkMedia;
use Illuminate\Foundation\Http\FormRequest;

class DesignerWorkMediaContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        $isDesigner = method_exists($user, 'hasRole')
            ? $user->hasRole('designer')
            : $user->roles()->where('name', 'designer')->exists();

        if (! $isDesigner) {
            return false;
        }

        $work = $this->route('work');
        $media = $this->route('media');
        $workId = $work instanceof Work ? $work->getKey() : $work;
        $mediaId = $media instanceof WorkMedia ? $media->getKey() : $media;

        $ownedWork = Work::query()
            ->whereKey($workId)
            ->where('designer_id', $user->getKey())
            ->first();

        abort_if(! $ownedWork, 404);
        abort_unless(WorkMedia::query()
            ->whereKey($mediaId)
            ->where('work_id', $ownedWork->getKey())
            ->exists(), 404);

        return true;
    }

    public function rules(): array
    {
        return [];
    }
}
