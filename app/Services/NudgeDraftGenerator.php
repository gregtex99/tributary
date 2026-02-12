<?php

namespace App\Services;

use App\Models\ActionItem;

class NudgeDraftGenerator
{
    public function generateNudge(ActionItem $item): string
    {
        $subject = $this->subject($item);
        $person = $this->person($item);
        $hours = $this->hoursWaiting($item);

        if ($item->current_state === 'action_needed' || $item->ball_with === 'greg') {
            return sprintf(
                '%s replied to %s %d hours ago. Ready for your review?!',
                $person,
                $subject,
                $hours,
            );
        }

        if ($item->current_state === 'waiting_on_calendar' || $item->source === 'calendar') {
            return sprintf(
                'Reminder: %s sent a calendar invite for %s. Need to accept/decline? It\'s been %d hours!',
                $person,
                $subject,
                $hours,
            );
        }

        return sprintf(
            'Hey %s, just circling back on %s. It\'s been %d hours. Let me know if you need anything from me!',
            $person,
            $subject,
            $hours,
        );
    }

    public function generateEscalation(ActionItem $item, int $nudges): string
    {
        $person = $this->person($item);

        return sprintf(
            'Going cold — %s hasn\'t responded about %s after %d nudges. Greg, can you take a look?!',
            $person,
            $this->subject($item),
            $nudges,
        );
    }

    private function person(ActionItem $item): string
    {
        $candidate = $item->waiting_for ?: $item->ball_with ?: 'they';

        return trim($candidate) !== '' ? trim($candidate) : 'they';
    }

    private function subject(ActionItem $item): string
    {
        $subject = trim($item->title);

        return $subject !== '' ? '"'.$subject.'"' : 'this item';
    }

    private function hoursWaiting(ActionItem $item): int
    {
        return max(1, $item->created_at?->diffInHours(now()) ?? 1);
    }
}
