<?php

namespace Database\Seeders;

use App\Models\ActionItem;
use App\Models\NudgeRule;
use Illuminate\Database\Seeder;

class NudgeRuleSeeder extends Seeder
{
    public function run(): void
    {
        $orgIds = ActionItem::query()
            ->distinct()
            ->pluck('org_id')
            ->filter()
            ->values()
            ->all();

        if (! in_array('default', $orgIds, true)) {
            $orgIds[] = 'default';
        }

        $rules = [
            'email' => ['first_nudge_hours' => 48, 'repeat_nudge_hours' => 48, 'max_nudges' => 3],
            'calendar' => ['first_nudge_hours' => 24, 'repeat_nudge_hours' => 24, 'max_nudges' => 2],
            'slack' => ['first_nudge_hours' => 24, 'repeat_nudge_hours' => 48, 'max_nudges' => 3],
            'linear' => ['first_nudge_hours' => 72, 'repeat_nudge_hours' => 72, 'max_nudges' => 2],
            'default' => ['first_nudge_hours' => 48, 'repeat_nudge_hours' => 48, 'max_nudges' => 3],
        ];

        foreach ($orgIds as $orgId) {
            foreach ($rules as $itemType => $rule) {
                NudgeRule::updateOrCreate(
                    [
                        'org_id' => $orgId,
                        'item_type' => $itemType,
                    ],
                    [
                        ...$rule,
                        'auto_send' => false,
                        'escalation_after_nudges' => max(1, $rule['max_nudges'] - 1),
                    ]
                );
            }
        }
    }
}
