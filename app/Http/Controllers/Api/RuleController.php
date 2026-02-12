<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NudgeRuleResource;
use App\Models\NudgeRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RuleController extends Controller
{
    public function index(Request $request)
    {
        $query = NudgeRule::query();

        if ($request->filled('org_id')) {
            $query->where('org_id', $request->string('org_id')->toString());
        }

        return NudgeRuleResource::collection($query->orderBy('item_type')->paginate(100));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'id' => ['nullable', 'string', 'uuid'],
            'org_id' => ['required', 'string', 'max:255'],
            'item_type' => ['required', 'string', 'max:255'],
            'first_nudge_hours' => ['nullable', 'integer', 'min:1'],
            'repeat_nudge_hours' => ['nullable', 'integer', 'min:1'],
            'max_nudges' => ['nullable', 'integer', 'min:1'],
            'auto_send' => ['nullable', 'boolean'],
            'escalation_after_nudges' => ['nullable', 'integer', 'min:0'],
        ]);

        $attributes = [
            'org_id' => $validated['org_id'],
            'item_type' => $validated['item_type'],
        ];

        if (isset($validated['id'])) {
            $attributes = ['id' => $validated['id']];
        }

        $rule = NudgeRule::query()->updateOrCreate(
            $attributes,
            [
                'org_id' => $validated['org_id'],
                'item_type' => $validated['item_type'],
                'first_nudge_hours' => $validated['first_nudge_hours'] ?? 48,
                'repeat_nudge_hours' => $validated['repeat_nudge_hours'] ?? 48,
                'max_nudges' => $validated['max_nudges'] ?? 3,
                'auto_send' => $validated['auto_send'] ?? false,
                'escalation_after_nudges' => $validated['escalation_after_nudges'] ?? 2,
            ]
        );

        return (new NudgeRuleResource($rule))
            ->response()
            ->setStatusCode(JsonResponse::HTTP_CREATED);
    }

    public function destroy(string $id): JsonResponse
    {
        $rule = NudgeRule::query()->findOrFail($id);
        $rule->delete();

        return response()->json([
            'message' => 'Rule deleted successfully.',
            'id' => $id,
        ]);
    }
}
