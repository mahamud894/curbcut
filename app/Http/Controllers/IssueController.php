<?php

namespace App\Http\Controllers;

use App\Compliance\IssueWorkflow;
use App\Compliance\Wcag;
use App\Models\Activity;
use App\Models\Issue;
use App\Models\Site;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class IssueController extends Controller
{
    public function show(Site $site, Issue $issue): Response
    {
        $this->authorize('view', $site);
        abort_unless($issue->site_id === $site->id, 404);
        $issue->load(['page:id,url,title', 'assignee:id,name', 'dismisser:id,name', 'comments.user:id,name']);

        $activity = Activity::where('site_id', $site->id)
            ->where('subject_type', $issue->getMorphClass())->where('subject_id', $issue->id)
            ->with('user:id,name')->latest('id')->get()
            ->map(fn (Activity $a) => ['id' => $a->id, 'action' => $a->action, 'properties' => $a->properties, 'user' => $a->user?->name, 'created_at' => $a->created_at?->toIso8601String()]);

        return Inertia::render('sites/issue', [
            'site' => SiteController::summary($site->load(['latestScan', 'latestCompletedScan'])),
            'issue' => [
                ...SiteController::issueRow($issue),
                'assignee' => $issue->assignee?->only(['id', 'name']),
                'due_on' => $issue->due_on?->toDateString(),
                'overdue' => $issue->isOverdue(),
                'dismissed_reason' => $issue->dismissed_reason,
                'dismissed_note' => $issue->dismissed_note,
                'dismissed_by' => $issue->dismisser?->name,
                'dismissed_at' => $issue->dismissed_at?->toIso8601String(),
                'criteria' => collect($issue->wcag)->map(fn ($t) => Wcag::fromAxeTag($t))->filter(fn ($id) => $id && Wcag::exists($id))
                    ->unique()->values()->map(fn ($id) => ['id' => $id, 'title' => Wcag::title($id), 'url' => Wcag::understandingUrl($id)]),
                'comments' => $issue->comments->map(fn ($c) => ['id' => $c->id, 'body' => $c->body, 'user' => $c->user?->name, 'created_at' => $c->created_at?->toIso8601String()]),
            ],
            'activity' => $activity,
            'team' => $site->team()->map->only(['id', 'name']),
            'dismissReasons' => Issue::DISMISS_REASONS,
        ]);
    }

    public function update(Request $request, Site $site, Issue $issue, IssueWorkflow $workflow): RedirectResponse
    {
        $this->authorize('update', $site);
        abort_unless($issue->site_id === $site->id, 404);

        $workflow->apply($site, [$issue], $this->validatedChanges($request, $site), $request->user());

        return back()->with('success', 'Issue updated.');
    }

    /** Apply the same change to many issues: selected ids, or every issue of one rule. */
    public function bulk(Request $request, Site $site, IssueWorkflow $workflow): RedirectResponse
    {
        $this->authorize('update', $site);
        $request->validate([
            'ids' => ['required_without:rule_id', 'array', 'max:1000'],
            'ids.*' => ['integer'],
            'rule_id' => ['required_without:ids', 'string', 'max:100'],
        ]);

        $issues = $site->issues()->whereIn('status', ['open', 'dismissed'])
            ->when($request->input('ids'), fn ($q, $ids) => $q->whereIn('id', $ids))
            ->when($request->input('rule_id'), fn ($q, $rule) => $q->where('rule_id', $rule)->where('status', 'open'))
            ->get();

        $count = $workflow->apply($site, $issues, $this->validatedChanges($request, $site), $request->user());

        return back()->with('success', $count === 1 ? '1 issue updated.' : "{$count} issues updated.");
    }

    public function comment(Request $request, Site $site, Issue $issue): RedirectResponse
    {
        $this->authorize('update', $site);
        abort_unless($issue->site_id === $site->id, 404);
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']]);

        $issue->comments()->create(['user_id' => $request->user()->id, 'body' => $data['body']]);
        Activity::record($site, $request->user(), 'issue.commented', $issue, ['issue' => $issue->help]);

        return back()->with('success', 'Comment added.');
    }

    /** @return array<string, mixed> */
    private function validatedChanges(Request $request, Site $site): array
    {
        $data = $request->validate([
            'assignee_id' => ['sometimes', 'nullable', 'integer', Rule::in($site->team()->pluck('id')->all())],
            'due_on' => ['sometimes', 'nullable', 'date'],
            'dismiss' => ['sometimes', 'nullable', 'array'],
            'dismiss.reason' => ['required_with:dismiss', Rule::in(array_keys(Issue::DISMISS_REASONS))],
            'dismiss.note' => ['nullable', 'string', 'max:2000'],
            'restore' => ['sometimes', 'boolean'],
        ], ['assignee_id.in' => 'Issues can only be assigned to people who have access to this site.']);

        return array_intersect_key($data, array_flip(['assignee_id', 'due_on', 'dismiss', 'restore']));
    }
}
