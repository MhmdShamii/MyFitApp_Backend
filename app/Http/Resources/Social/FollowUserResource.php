<?php

namespace App\Http\Resources\Social;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class FollowUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'first_name'   => $this->first_name,
            'last_name'    => $this->last_name,
            'avatar'       => $this->resolveAvatarUrl(),
            'role'         => $this->role,
            'is_following' => Auth::user()->following()->where('followed_id', $this->id)->exists(),
        ];
    }

    private function resolveAvatarUrl(): string
    {
        if ($this->image === null || $this->image === 'default.png') {
            return Storage::disk('s3')->url('avatars/default.png');
        }

        if (str_starts_with($this->image, 'http')) {
            return $this->image;
        }

        return Storage::disk('s3')->url($this->image);
    }
}
