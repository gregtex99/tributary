<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\NudgeRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsPageController extends Controller
{
    public function index(): Response
    {
        $rules = NudgeRule::query()->orderBy('item_type')->get();

        $defaultOrgId = $rules->first()?->org_id ?? 'default';

        return Inertia::render('Settings/Index', [
            'rules' => $rules,
            'defaultOrgId' => $defaultOrgId,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'id' => ['nullable', 'string', 'uuid'],
            'org_id' => ['required', 'string', 'max:255'],
            'item_type' => ['required', 'string', 'max:255'],
            'first_nudge_hours' => ['required', 'integer', 'min:1'],
            'repeat_nudge_hours' => ['required', 'integer', 'min:1'],
            'max_nudges' => ['required', 'integer', 'min:1'],
            'auto_send' => ['nullable', 'boolean'],
        ]);

        $query = NudgeRule::query();

        if (! empty($validated['id'])) {
            $query->where('id', $validated['id']);
            $rule = $query->first();

            if ($rule !== null) {
                $rule->fill([
                    'org_id' => $validated['org_id'],
                    'item_type' => $validated['item_type'],
                    'first_nudge_hours' => $validated['first_nudge_hours'],
                    'repeat_nudge_hours' => $validated['repeat_nudge_hours'],
                    'max_nudges' => $validated['max_nudges'],
                    'auto_send' => (bool) ($validated['auto_send'] ?? false),
                ]);
                $rule->save();

                return back()->with('success', 'Rule updated.');
            }
        }

        NudgeRule::query()->updateOrCreate(
            [
                'org_id' => $validated['org_id'],
                'item_type' => $validated['item_type'],
            ],
            [
                'first_nudge_hours' => $validated['first_nudge_hours'],
                'repeat_nudge_hours' => $validated['repeat_nudge_hours'],
                'max_nudges' => $validated['max_nudges'],
                'auto_send' => (bool) ($validated['auto_send'] ?? false),
            ]
        );

        return back()->with('success', 'Rule saved.');
    }

    public function destroy(NudgeRule $rule): RedirectResponse
    {
        $rule->delete();

        return back()->with('success', 'Rule deleted.');
    }
}
