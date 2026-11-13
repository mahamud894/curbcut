<?php

namespace App\Http\Controllers;

use App\Compliance\Conformance;
use App\Models\Activity;
use App\Models\Site;
use App\Models\Statement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class StatementController extends Controller
{
    public function edit(Site $site, Conformance $conformance): Response
    {
        $this->authorize('view', $site);
        $statement = $this->statementFor($site);

        return Inertia::render('sites/statement', [
            'site' => SiteController::summary($site->load(['latestScan', 'latestCompletedScan'])),
            'statement' => $this->statementData($statement),
            'preview' => $this->publicData($site, $statement, $conformance),
            'conformanceLabels' => Conformance::LABELS,
            'language' => $site->language,
        ]);
    }

    public function update(Request $request, Site $site): RedirectResponse
    {
        $this->authorize('update', $site);
        $data = $request->validate([
            'organisation' => ['nullable', 'string', 'max:200'],
            'contact_email' => ['nullable', 'email', 'max:200'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'conformance_override' => ['nullable', Rule::in(Statement::CONFORMANCE)],
            'additional_limitations' => ['nullable', 'string', 'max:10000'],
            'feedback_process' => ['nullable', 'string', 'max:5000'],
            'enforcement_procedure' => ['nullable', 'string', 'max:5000'],
            'language' => ['nullable', Rule::in(array_keys(config('locales')))],
        ]);

        if (! empty($data['language'])) {
            $site->update(['language' => $data['language']]);
        }
        unset($data['language']);
        $this->statementFor($site)->update($data);
        Activity::record($site, $request->user(), 'statement.updated', $site->statement);

        return back()->with('success', tr('Statement saved.'));
    }

    public function publish(Request $request, Site $site): RedirectResponse
    {
        $this->authorize('update', $site);
        $statement = $this->statementFor($site);
        $request->validate(['published' => ['required', 'boolean']]);

        if ($request->boolean('published') && (! $statement->organisation || ! $statement->contact_email)) {
            return back()->with('error', tr('Add the organisation name and a contact email before publishing.'));
        }

        $statement->update(['published_at' => $request->boolean('published') ? ($statement->published_at ?? now()) : null]);
        Activity::record($site, $request->user(), $statement->isPublished() ? 'statement.published' : 'statement.unpublished', $statement);

        return back()->with('success', $statement->isPublished() ? tr('Statement published.') : tr('Statement unpublished.'));
    }

    /** The public, unauthenticated statement page. */
    public function public(string $token, Conformance $conformance): Response
    {
        $statement = Statement::where('public_token', $token)->whereNotNull('published_at')->firstOrFail();
        // The public page is shown in the statement's language, whatever the visitor's browser says.
        app()->setLocale($statement->site->language);

        return Inertia::render('statement/public', [
            'statement' => $this->publicData($statement->site, $statement, $conformance),
        ]);
    }

    private function statementFor(Site $site): Statement
    {
        return $site->statement ?? $site->statement()->create([
            'feedback_process' => tr('We welcome your feedback on the accessibility of this website. If you find a barrier, please contact us and we will try to respond within 5 business days.', [], $site->language),
        ]);
    }

    private function statementData(Statement $statement): array
    {
        return $statement->only([
            'organisation', 'contact_email', 'contact_phone', 'conformance_override',
            'additional_limitations', 'feedback_process', 'enforcement_procedure',
        ]) + [
            'published' => $statement->isPublished(),
            'published_at' => $statement->published_at?->toIso8601String(),
            'public_url' => route('statement.public', $statement->public_token),
        ];
    }

    /** Everything the public page shows, generated from the latest scan and checklist. */
    private function publicData(Site $site, Statement $statement, Conformance $conformance): array
    {
        $assessment = $conformance->assess($site);
        $status = $statement->conformance_override ?? $assessment['suggested'];
        $lastScan = $site->latestCompletedScan()->first();
        $reviewed = collect([$lastScan?->finished_at, $assessment['last_tested_at'] ? now()->parse($assessment['last_tested_at']) : null, $statement->updated_at])->filter()->max();

        return [
            'site_name' => $site->name,
            'site_url' => $site->url,
            'organisation' => $statement->organisation ?: $site->name,
            'contact_email' => $statement->contact_email,
            'contact_phone' => $statement->contact_phone,
            'standard' => 'WCAG 2.2 level AA',
            'conformance' => $status,
            'conformance_label' => Conformance::LABELS[$status],
            'is_override' => $statement->conformance_override !== null,
            'suggested' => $assessment['suggested'],
            'assessment_complete' => $assessment['complete'],
            'checklist_counts' => $assessment['counts'],
            'failing' => $assessment['failing'],
            'automated_rules' => array_slice($assessment['automated_rules'], 0, 20),
            'additional_limitations' => $statement->additional_limitations,
            'feedback_process' => $statement->feedback_process,
            'enforcement_procedure' => $statement->enforcement_procedure,
            'last_scan_at' => $lastScan?->finished_at?->toIso8601String(),
            'pages_scanned' => $lastScan?->pages_scanned,
            'reviewed_at' => $reviewed?->toIso8601String(),
            'published_at' => $statement->published_at?->toIso8601String(),
        ];
    }
}
