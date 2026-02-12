<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class NudgeRule extends Model
{
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'org_id',
        'item_type',
        'first_nudge_hours',
        'repeat_nudge_hours',
        'max_nudges',
        'auto_send',
        'escalation_after_nudges',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'auto_send' => 'boolean',
        ];
    }
}
