<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\NudgeRule */
class NudgeRuleResource extends JsonResource
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
            'item_type' => $this->item_type,
            'first_nudge_hours' => $this->first_nudge_hours,
            'repeat_nudge_hours' => $this->repeat_nudge_hours,
            'max_nudges' => $this->max_nudges,
            'auto_send' => $this->auto_send,
            'escalation_after_nudges' => $this->escalation_after_nudges,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
