<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\NudgeLog */
class NudgeLogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action_item_id' => $this->action_item_id,
            'nudge_number' => $this->nudge_number,
            'channel' => $this->channel,
            'message_text' => $this->message_text,
            'approved_at' => $this->approved_at?->toIso8601String(),
            'sent_at' => $this->sent_at?->toIso8601String(),
            'response_detected_at' => $this->response_detected_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
