<?php

namespace App\Reports;

use App\Compliance\Conformance;
use App\Compliance\Wcag;
use App\Models\Issue;
use App\Models\Scan;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Collects everything that goes into a site's audit report. */
class SiteReport
{
    public function __construct(private Conformance $conformance) {}

    /** @return array<string, mixed> */
    public function build(Site $site, User $generatedBy): array
    {
        $site->loadMissing('organization');
        $scan = $site->scans()->where('status', Scan::COMPLETED)->latest('id')->first();
        $assessment = $this->conformance->assess($site);
        $statement = $site->statement;
        $status = $statement?->conformance_override ?? $assessment['suggested'];

        $rules = Issue::where('site_id', $site->id)->where('status', 'open')
            ->select('rule_id', 'help', 'impact', 'help_url', DB::raw('count(*) as total'), DB::raw('count(distinct page_id) as pages'))
            ->groupBy('rule_id', 'help', 'impact', 'help_url')->get()
            ->sortBy([fn ($a, $b) => array_search($a->impact, Issue::IMPACTS) <=> array_search($b->impact, Issue::IMPACTS), fn ($a, $b) => $b->total <=> $a->total])
            ->values()
            ->map(function ($rule) use ($site) {
                $examples = Issue::where('site_id', $site->id)->where('status', 'open')->where('rule_id', $rule->rule_id)
                    ->with(['page:id,url', 'assignee:id,name'])->limit(5)->get();

                return [
                    'rule_id' => $rule->rule_id,
                    'help' => $rule->help,
                    'impact' => $rule->impact,
                    'help_url' => $rule->help_url,
                    'total' => (int) $rule->total,
                    'pages' => (int) $rule->pages,
                    'criteria' => collect($examples->first()?->wcag ?? [])->map(fn ($t) => Wcag::fromAxeTag($t))->filter()->unique()->values()->all(),
                    'examples' => $examples->map(fn (Issue $i) => [
                        'page' => $i->page->url,
                        'target' => $i->target,
                        'html' => $i->html,
                        'assignee' => $i->assignee?->name,
                        'due_on' => $i->due_on?->toDateString(),
                    ])->all(),
                ];
            });

        return [
            'site' => $site,
            'workspace' => $site->organization?->name,
            'generated_at' => now(),
            'generated_by' => $generatedBy->name,
            'scan' => $scan,
            'history' => $site->scans()->where('status', Scan::COMPLETED)->latest('id')->limit(10)->get()->reverse()->values(),
            'assessment' => $assessment,
            'conformance' => $status,
            'conformance_label' => Conformance::LABELS[$status],
            'conformance_is_override' => $statement?->conformance_override !== null,
            'statement_url' => $statement?->isPublished() ? route('statement.public', $statement->public_token) : null,
            'rules' => $rules,
            'dismissed' => Issue::where('site_id', $site->id)->where('status', 'dismissed')->with(['page:id,url', 'dismisser:id,name'])->orderBy('rule_id')->get(),
            'fixed_recently' => $scan ? Issue::where('site_id', $site->id)->where('fixed_in_scan_id', $scan->id)->count() : 0,
            'pages' => $site->pages()
                ->withCount(['issues as open_issues' => fn ($q) => $q->where('status', 'open')])
                ->withCount(['issues as critical' => fn ($q) => $q->where('status', 'open')->where('impact', 'critical')])
                ->orderByDesc('open_issues')->limit(25)->get(),
            'page_count' => $site->pages()->count(),
            'assigned' => Issue::where('site_id', $site->id)->where('status', 'open')->whereNotNull('assignee_id')->count(),
            'overdue' => Issue::where('site_id', $site->id)->where('status', 'open')->whereNotNull('due_on')->whereDate('due_on', '<', today())->count(),
            'activity' => $site->activities()->with('user:id,name')->latest('id')->limit(40)->get(),
        ];
    }
}
