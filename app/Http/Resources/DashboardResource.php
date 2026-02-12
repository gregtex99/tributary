<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'items_by_state' => $this->resource['items_by_state'] ?? [],
            'pending_nudges' => $this->resource['pending_nudges'] ?? 0,
            'signals_today' => $this->resource['signals_today'] ?? 0,
            'avg_resolution_seconds' => $this->resource['avg_resolution_seconds'],
        ];
    }
}
