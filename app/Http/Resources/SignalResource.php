<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Signal */
class SignalResource extends JsonResource
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
            'source' => $this->source,
            'source_ref' => $this->source_ref,
            'signal_type' => $this->signal_type,
            'actor' => $this->actor,
            'detected_at' => $this->detected_at?->toIso8601String(),
            'matched_item_id' => $this->matched_item_id,
            'payload' => $this->payload,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'matched_item' => new ActionItemResource($this->whenLoaded('actionItem')),
        ];
    }
}
