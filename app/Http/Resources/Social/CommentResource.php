<?php

namespace App\Http\Resources\Social;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class CommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'body'          => $this->body,
            'created_at'    => $this->created_at->toISOString(),
            'author'        => [
                'id'         => $this->user->id,
                'first_name' => $this->user->first_name,
                'last_name'  => $this->user->last_name,
                'avatar'     => $this->resolveAvatarUrl($this->user->image),
                'role'       => $this->user->role,
            ],
            'replies_count' => $this->whenCounted('replies_count'),
            'reply_preview' => $this->when(
                $this->relationLoaded('replyPreview'),
                fn() => CommentResource::collection($this->replyPreview)
            ),
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
