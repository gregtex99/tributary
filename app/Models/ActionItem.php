<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ActionItem extends Model
{
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'org_id',
        'user_id',
        'source',
        'source_ref',
        'title',
        'description',
        'current_state',
        'ball_with',
        'waiting_for',
        'nudge_after_hours',
        'next_nudge_at',
        'nudge_count',
        'max_nudges',
        'resolved_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'next_nudge_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function transitions(): HasMany
    {
        return $this->hasMany(ActionItemTransition::class);
    }

    public function nudges(): HasMany
    {
        return $this->hasMany(NudgeLog::class);
    }

    public function signals(): HasMany
    {
        return $this->hasMany(Signal::class, 'matched_item_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotIn('current_state', ['done', 'cancelled']);
    }

    public function scopeNeedsNudge(Builder $query): Builder
    {
        return $query
            ->active()
            ->whereNotNull('next_nudge_at')
            ->where('next_nudge_at', '<=', now())
            ->whereColumn('nudge_count', '<', 'max_nudges');
    }

    public function scopeWaitingOn(Builder $query, string $type): Builder
    {
        return $query->where('waiting_for', $type);
    }
}
