<?php

namespace App\Http\Controllers;

use App\Compliance\Conformance;
use App\Compliance\Wcag;
use App\Models\Activity;
use App\Models\ChecklistItem;
use App\Models\Site;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ChecklistController extends Controller
{
    public function index(Site $site, Conformance $conformance): Response
    {
        $this->authorize('view', $site);

        return Inertia::render('sites/checklist', [
            'site' => SiteController::summary($site->load(['latestScan', 'latestCompletedScan'])),
            'assessment' => $conformance->assess($site),
            'principles' => Wcag::PRINCIPLES,
        ]);
    }

    public function update(Request $request, Site $site, string $criterion): RedirectResponse
    {
        $this->authorize('update', $site);
        abort_unless(Wcag::exists($criterion), 404);
        $data = $request->validate([
            'status' => ['required', Rule::in(ChecklistItem::STATUSES)],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $item = $site->checklistItems()->firstOrNew(['criterion' => $criterion]);
        $before = $item->exists ? $item->only(['status', 'notes']) : ['status' => 'not_tested', 'notes' => null];
        $item->fill([
            'status' => $data['status'],
            'notes' => $data['notes'] ?? null,
            'tested_by' => $data['status'] === 'not_tested' ? null : $request->user()->id,
            'tested_at' => $data['status'] === 'not_tested' ? null : now(),
        ])->save();

        if ($before['status'] !== $item->status || $before['notes'] !== $item->notes) {
            Activity::record($site, $request->user(), 'checklist.updated', $item, [
                'criterion' => $criterion,
                'title' => Wcag::title($criterion),
                'from' => $before['status'],
                'to' => $item->status,
            ]);
        }

        return back()->with('success', "{$criterion} {$this->label($item->status)}.");
    }

    private function label(string $status): string
    {
        return ['pass' => 'marked as passing', 'fail' => 'marked as failing', 'not_applicable' => 'marked as not applicable', 'not_tested' => 'reset to not tested'][$status];
    }
}
