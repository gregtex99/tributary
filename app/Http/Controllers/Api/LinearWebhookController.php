<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LinearWebhookController extends Controller
{
    private const GREG_USER_ID = '29012186-36e0-403c-bbae-b63688fae7bd';
    private const DISCORD_CHANNEL_ID = '1471897266490052770';
    private const DISCORD_TRINITY_CHANNEL_ID = '1466581983063707901';
    private const COMMS_PROJECT_ID = 'c938600c-7a87-4ff1-898e-dcedac03f3d0';

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

        // Check for comms send approval (Comment on Communications project issue)
        if ($type === 'Comment' && $action === 'create') {
            $this->checkCommsSendApproval($data, $payload);
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
            return "🗑️ **{$identifier}** deleted: {$title}";
        }

        return null;
    }

    private function formatComment(array $data, ?string $url): string
    {
        $identifier = $data['issue']['identifier'] ?? 'Unknown';
        $title = $data['issue']['title'] ?? '';
        $body = $data['body'] ?? '';
        $issueUrl = $this->getIssueUrl($identifier);

        // Truncate long comments
        if (mb_strlen($body) > 500) {
            $body = mb_substr($body, 0, 497) . '...';
        }

        return "💬 Greg commented on [**{$identifier}**](<{$issueUrl}>): {$title}\n\n> {$body}";
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

        $issueUrl = $this->getIssueUrl($identifier);
        $changeStr = implode("\n", $changes);
        return "✏️ Greg updated [**{$identifier}**](<{$issueUrl}>): {$title}\n{$changeStr}";
    }

    private function formatIssueCreate(array $data, ?string $url): string
    {
        $identifier = $data['identifier'] ?? 'Unknown';
        $title = $data['title'] ?? '';
        $stateName = $data['state']['name'] ?? '';
        $issueUrl = $this->getIssueUrl($identifier);

        $msg = "🆕 Greg created [**{$identifier}**](<{$issueUrl}>): {$title}";

        if ($stateName) {
            $msg .= " [{$stateName}]";
        }

        return $msg;
    }

    private function getIssueUrl(string $identifier): string
    {
        return "https://linear.app/absoluteio/issue/{$identifier}";
    }

    private function checkCommsSendApproval(array $data, array $payload): void
    {
        // Check if this comment is on a Communications project issue
        $issueProjectId = $data['issue']['project']['id'] ?? null;
        if ($issueProjectId !== self::COMMS_PROJECT_ID) {
            return;
        }

        $body = strtolower(trim($data['body'] ?? ''));
        $identifier = $data['issue']['identifier'] ?? 'Unknown';
        $title = $data['issue']['title'] ?? '';
        $issueId = $data['issue']['id'] ?? '';

        // Detect approval commands
        $action = null;
        if (preg_match('/^send$/i', $body) || preg_match('/^send\s*it$/i', $body) || preg_match('/^approved?$/i', $body) || preg_match('/^lgtm$/i', $body)) {
            $action = 'send';
        } elseif (preg_match('/^skip$/i', $body) || preg_match('/^ignore$/i', $body) || preg_match('/^cancel$/i', $body)) {
            $action = 'skip';
        } elseif (preg_match('/^research/i', $body)) {
            $action = 'research';
        }

        if (!$action) {
            // Check if it's a custom draft (longer text that isn't a command)
            if (mb_strlen($data['body'] ?? '') > 10) {
                $action = 'custom_draft';
            } else {
                return;
            }
        }

        // Send special signal to #trinity for Trinity to act on
        $signal = "📬 **COMMS_SEND_SIGNAL**\n";
        $signal .= "**Action:** {$action}\n";
        $signal .= "**Issue:** [{$identifier}](<https://linear.app/absoluteio/issue/{$identifier}>)\n";
        $signal .= "**Title:** {$title}\n";
        $signal .= "**Issue ID:** {$issueId}\n";

        if ($action === 'custom_draft') {
            $signal .= "**Custom Draft:**\n> " . ($data['body'] ?? '');
        }

        $this->sendToDiscordChannel(self::DISCORD_TRINITY_CHANNEL_ID, $signal);
    }

    private function sendToDiscordChannel(string $channelId, string $message): void
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bot ' . config('services.discord.bot_token'),
        ])->post("https://discord.com/api/v10/channels/{$channelId}/messages", [
            'content' => $message,
        ]);

        if (!$response->successful()) {
            Log::error('Discord relay failed', [
                'channel' => $channelId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        }
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
