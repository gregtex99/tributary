<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActionItemTransition extends Model
{
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'action_item_id',
        'from_state',
        'to_state',
        'trigger',
        'signal_data',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'signal_data' => 'array',
        ];
    }

    public function actionItem(): BelongsTo
    {
        return $this->belongsTo(ActionItem::class);
    }
}
