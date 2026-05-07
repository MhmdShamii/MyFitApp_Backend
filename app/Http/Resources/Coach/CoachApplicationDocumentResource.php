<?php

namespace App\Http\Resources\Coach;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class CoachApplicationDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'type'          => $this->type,
            'original_name' => $this->original_name,
            'url'           => Storage::disk('s3')->temporaryUrl($this->file_path, now()->addMinutes(30)),
        ];
    }
}
