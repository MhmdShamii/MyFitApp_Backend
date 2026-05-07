<?php

namespace App\Http\Resources\Coach;

use App\Enums\CoachApplicationStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CoachApplicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'status'           => $this->status,
            'description'      => $this->description,
            'rejection_reason' => $this->rejection_reason,
            'reviewed_at'      => $this->reviewed_at,
            'can_reapply_at'   => $this->status === CoachApplicationStatus::REJECTED
                ? $this->reviewed_at?->addDays(30)->toDateString()
                : null,
            'documents'        => CoachApplicationDocumentResource::collection($this->whenLoaded('documents')),
            'applicant'        => $this->whenLoaded('user', fn() => [
                'id'         => $this->user->id,
                'first_name' => $this->user->first_name,
                'last_name'  => $this->user->last_name,
                'email'      => $this->user->email,
            ]),
            'submitted_at'     => $this->created_at,
        ];
    }
}
