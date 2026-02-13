<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LinearWebhookController extends Controller
{
    private const GREG_USER_ID = '29012186-36e0-403c-bbae-b63688fae7bd';
    private const DISCORD_CHANNEL_ID = '1466581983063707901';

    public function __invoke(Request $request)
    {
        $payload = $request->all();
        $action = $payload['action'] ?? null;
        $type = $payload['type'] ?? null;
        $data = $payload['data'] ?? [];

        Log::info('Linear webhook received', ['action' => $action, 'type' => $type]);

        // Determine actor ID
        $actorId = $this->getActorId($payload);

        if ($actorId !== self::GREG_USER_ID) {
            return response()->json(['status' => 'ignored', 'reason' => 'not from Greg']);
        }

        $message = $this->formatMessage($action, $type, $data, $payload);

        if (!$message) {
            return response()->json(['status' => 'ignored', 'reason' => 'uninteresting event']);
        }

        $this->sendToDiscord($message);

        return response()->json(['status' => 'relayed']);
    }

    private function getActorId(array $payload): ?string
    {
        // Linear puts the actor in different places depending on type
        // Comments: data.user.id is the commenter
        // Issues: actor.id is who made the change (newer payloads)
        //         data.creatorId or updatedBy may also exist
        if (isset($payload['actor']['id'])) {
            return $payload['actor']['id'];
        }

        // For comments, the user who wrote it
        if (($payload['type'] ?? '') === 'Comment' && isset($payload['data']['user']['id'])) {
            return $payload['data']['user']['id'];
        }

        return null;
    }

    private function formatMessage(string $action, string $type, array $data, array $payload): ?string
    {
        $url = $payload['url'] ?? $data['url'] ?? null;

        if ($type === 'Comment' && $action === 'create') {
            return $this->formatComment($data, $url);
        }

        if ($type === 'Issue' && $action === 'update') {
            return $this->formatIssueUpdate($data, $payload, $url);
        }

        if ($type === 'Issue' && $action === 'create') {
            return $this->formatIssueCreate($data, $url);
        }

        if ($type === 'Issue' && $action === 'remove') {
            $identifier = $data['identifier'] ?? 'Unknown';
            $title = $data['title'] ?? '';
            return "📋 **Linear Update**\n🗑️ **{$identifier}** deleted: {$title}";
        }

        return null;
    }

    private function formatComment(array $data, ?string $url): string
    {
        $identifier = $data['issue']['identifier'] ?? 'Unknown';
        $title = $data['issue']['title'] ?? '';
        $body = $data['body'] ?? '';

        // Truncate long comments
        if (mb_strlen($body) > 500) {
            $body = mb_substr($body, 0, 497) . '...';
        }

        $msg = "📋 **Linear Update**\n💬 Greg commented on **{$identifier}**: {$title}\n\n> {$body}";

        if ($url) {
            $msg .= "\n\n<{$url}>";
        }

        return $msg;
    }

    private function formatIssueUpdate(array $data, array $payload, ?string $url): string
    {
        $identifier = $data['identifier'] ?? 'Unknown';
        $title = $data['title'] ?? '';
        $updatedFrom = $payload['updatedFrom'] ?? [];
        $changes = [];

        // State change
        if (isset($updatedFrom['stateId']) && isset($data['state']['name'])) {
            $changes[] = "→ State: **{$data['state']['name']}**";
        }

        // Priority change
        if (isset($updatedFrom['priority'])) {
            $priorityNames = [0 => 'No priority', 1 => 'Urgent', 2 => 'High', 3 => 'Medium', 4 => 'Low'];
            $newPriority = $priorityNames[$data['priority'] ?? 0] ?? 'Unknown';
            $changes[] = "→ Priority: **{$newPriority}**";
        }

        // Assignee change
        if (array_key_exists('assigneeId', $updatedFrom)) {
            $assigneeName = $data['assignee']['name'] ?? 'Unassigned';
            $changes[] = "→ Assignee: **{$assigneeName}**";
        }

        // Label change
        if (isset($updatedFrom['labelIds'])) {
            $labelNames = [];
            foreach (($data['labels']['nodes'] ?? []) as $label) {
                $labelNames[] = $label['name'] ?? '';
            }
            $labelStr = $labelNames ? implode(', ', $labelNames) : 'None';
            $changes[] = "→ Labels: **{$labelStr}**";
        }

        if (empty($changes)) {
            return null;
        }

        $changeStr = implode("\n", $changes);
        $msg = "📋 **Linear Update**\n✏️ Greg updated **{$identifier}**: {$title}\n{$changeStr}";

        if ($url) {
            $msg .= "\n\n<{$url}>";
        }

        return $msg;
    }

    private function formatIssueCreate(array $data, ?string $url): string
    {
        $identifier = $data['identifier'] ?? 'Unknown';
        $title = $data['title'] ?? '';
        $stateName = $data['state']['name'] ?? '';

        $msg = "📋 **Linear Update**\n🆕 Greg created **{$identifier}**: {$title}";

        if ($stateName) {
            $msg .= " [{$stateName}]";
        }

        if ($url) {
            $msg .= "\n\n<{$url}>";
        }

        return $msg;
    }

    private function sendToDiscord(string $message): void
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bot ' . config('services.discord.bot_token'),
        ])->post('https://discord.com/api/v10/channels/' . self::DISCORD_CHANNEL_ID . '/messages', [
            'content' => $message,
        ]);

        if (!$response->successful()) {
            Log::error('Discord relay failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        }
    }
}
