<?php

namespace App\Http\Resources\User;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    private function resolveAvatarUrl(): string
    {
        if ($this->image === null || $this->image === 'default.png') {
            $default = $this->profile?->gender?->value === 'female'
                ? 'avatars/default_w.png'
                : 'avatars/default.png';

            return Storage::disk('s3')->url($default);
        }

        if (str_starts_with($this->image, 'http')) {
            return $this->image;
        }

        return Storage::disk('s3')->url($this->image);
    }

    private function resolveCoverImageUrl(): string
    {
        if ($this->cover_image === null || $this->cover_image === 'default_cover.png') {
            return Storage::disk('s3')->url('covers/default_cover.png');
        }

        return Storage::disk('s3')->url($this->cover_image);
    }

    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'first_name' => $this->first_name,
            'last_name'  => $this->last_name,
            'email'      => $this->email,
            'country' => [
                'code'       => $this->country?->code,
                'name'       => $this->country?->name,
            ],
            'image' => [
                'avatar'      => $this->resolveAvatarUrl(),
                'cover_image' => $this->resolveCoverImageUrl(),
            ],
            'verified'         => $this->email_verified_at !== null,
            'role'             => $this->role,
            'onboarding_step'  => $this->onboarding_step,
            'followers_count'  => $this->followers_count,
            'following_count'  => $this->following_count,
            'profile'          => new UserProfileResource($this->whenLoaded('profile')),
        ];
    }
}
