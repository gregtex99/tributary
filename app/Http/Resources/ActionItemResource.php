<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ActionItem */
class ActionItemResource extends JsonResource
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
            'org_id' => $this->org_id,
            'user_id' => $this->user_id,
            'source' => $this->source,
            'source_ref' => $this->source_ref,
            'title' => $this->title,
            'description' => $this->description,
            'current_state' => $this->current_state,
            'ball_with' => $this->ball_with,
            'waiting_for' => $this->waiting_for,
            'nudge_after_hours' => $this->nudge_after_hours,
            'next_nudge_at' => $this->next_nudge_at?->toIso8601String(),
            'nudge_count' => $this->nudge_count,
            'max_nudges' => $this->max_nudges,
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'transitions' => ActionItemTransitionResource::collection($this->whenLoaded('transitions')),
            'nudges' => NudgeLogResource::collection($this->whenLoaded('nudges')),
        ];
    }
}
