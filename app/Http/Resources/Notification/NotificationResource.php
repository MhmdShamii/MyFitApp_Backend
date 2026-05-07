<?php

namespace App\Http\Resources\Notification;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'type'       => $this->type->value,
            'data'       => $this->data,
            'actor'      => [
                'id'         => $this->actor->id,
                'first_name' => $this->actor->first_name,
                'last_name'  => $this->actor->last_name,
                'avatar'     => $this->resolveAvatarUrl($this->actor->image),
                'role'       => $this->actor->role,
            ],
            'created_at' => $this->created_at->toISOString(),
        ];
    }

    private function resolveAvatarUrl(?string $image): string
    {
        if ($image === null || $image === 'default.png') {
            return Storage::disk('s3')->url('avatars/default.png');
        }

        if (str_starts_with($image, 'http')) {
            return $image;
        }

        return Storage::disk('s3')->url($image);
    }
}
