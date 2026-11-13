<?php

namespace App\Compliance;

use App\Models\Issue;
use App\Models\Site;

/**
 * Works out where a site stands against WCAG 2.2 AA from the manual
 * checklist and the open automated issues, for the dashboard and the
 * accessibility statement.
 */
class Conformance
{
    public const LABELS = [
        'full' => 'Fully conformant',
        'partial' => 'Partially conformant',
        'none' => 'Not conformant',
    ];

    /**
     * @return array{
     *   criteria: list<array<string, mixed>>,
     *   counts: array{pass: int, fail: int, not_applicable: int, not_tested: int, total: int},
     *   suggested: string, complete: bool, failing: list<array{id: string, title: string, notes: ?string, automated: int}>,
     *   automated_rules: list<array{help: string, total: int, criteria: list<string>}>,
     *   last_tested_at: ?string
     * }
     */
    public function assess(Site $site): array
    {
        $items = $site->checklistItems()->with('tester:id,name')->get()->keyBy('criterion');
        $automated = $this->automatedIssuesByCriterion($site);

        $criteria = [];
        $counts = ['pass' => 0, 'fail' => 0, 'not_applicable' => 0, 'not_tested' => 0];
        $failing = [];
        foreach (Wcag::all() as $criterion) {
            $item = $items->get($criterion['id']);
            $status = $item?->status ?? 'not_tested';
            $counts[$status]++;
            $open = $automated[$criterion['id']] ?? 0;

            $criteria[] = $criterion + [
                'status' => $status,
                'notes' => $item?->notes,
                'tested_by' => $item?->tester?->name,
                'tested_at' => $item?->tested_at?->toIso8601String(),
                'automated_issues' => $open,
            ];

            // A criterion with open automated issues is failing, whatever the checklist says.
            if ($status === 'fail' || ($open > 0 && $status !== 'not_applicable')) {
                $failing[] = ['id' => $criterion['id'], 'title' => $criterion['title'], 'notes' => $status === 'fail' ? $item?->notes : null, 'automated' => $open];
            }
        }
        $counts['total'] = count($criteria);

        $applicable = $counts['total'] - $counts['not_applicable'];
        $suggested = match (true) {
            $failing === [] && $counts['not_tested'] === 0 => 'full',
            $applicable > 0 && count($failing) > $applicable / 2 => 'none',
            default => 'partial',
        };

        return [
            'criteria' => $criteria,
            'counts' => $counts,
            'suggested' => $suggested,
            'complete' => $counts['not_tested'] === 0,
            'failing' => $failing,
            'automated_rules' => $this->openRules($site),
            'last_tested_at' => $items->max('tested_at')?->toIso8601String(),
        ];
    }

    /** @return array<string, int> number of open issues per WCAG criterion */
    public function automatedIssuesByCriterion(Site $site): array
    {
        $counts = [];
        Issue::where('site_id', $site->id)->where('status', 'open')->select('wcag')->cursor()
            ->each(function (Issue $issue) use (&$counts) {
                foreach ($issue->wcag as $tag) {
                    if (($id = Wcag::fromAxeTag($tag)) && Wcag::exists($id)) {
                        $counts[$id] = ($counts[$id] ?? 0) + 1;
                    }
                }
            });

        return $counts;
    }

    /** @return list<array{help: string, total: int, criteria: list<string>}> */
    private function openRules(Site $site): array
    {
        return Issue::where('site_id', $site->id)->where('status', 'open')->get(['rule_id', 'help', 'wcag'])
            ->groupBy('rule_id')
            ->map(fn ($group) => [
                'help' => $group->first()->help,
                'total' => $group->count(),
                'criteria' => array_values(array_filter(array_map(fn ($t) => Wcag::fromAxeTag($t), $group->first()->wcag))),
            ])
            ->sortByDesc('total')->values()->all();
    }
}
