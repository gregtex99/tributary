<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Signal extends Model
{
    use HasUuids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'org_id',
        'source',
        'source_ref',
        'signal_type',
        'actor',
        'detected_at',
        'matched_item_id',
        'payload',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'detected_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    public function actionItem(): BelongsTo
    {
        return $this->belongsTo(ActionItem::class, 'matched_item_id');
    }
}
