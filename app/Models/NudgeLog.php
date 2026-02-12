<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NudgeLog extends Model
{
    use HasUuids;

    protected $table = 'nudge_log';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'action_item_id',
        'nudge_number',
        'channel',
        'message_text',
        'approved_at',
        'sent_at',
        'response_detected_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'sent_at' => 'datetime',
            'response_detected_at' => 'datetime',
        ];
    }

    public function actionItem(): BelongsTo
    {
        return $this->belongsTo(ActionItem::class);
    }
}
