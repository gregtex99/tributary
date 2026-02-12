<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ActionItemTransition */
class ActionItemTransitionResource extends JsonResource
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
            'from_state' => $this->from_state,
            'to_state' => $this->to_state,
            'trigger' => $this->trigger,
            'signal_data' => $this->signal_data,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
