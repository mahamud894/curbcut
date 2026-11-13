<?php

namespace App\Scanning;

use App\Models\Issue;
use App\Models\Page;
use App\Models\Scan;
use App\Models\ScanPage;
use App\Models\Site;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Runs one scan end to end: discovers pages, scans them in batches, records
 * issues and works out what is new and what has been fixed since last time.
 */
class ScanRunner
{
    /** Points deducted from a page's 100-point score per open issue. */
    public const IMPACT_WEIGHTS = ['critical' => 10, 'serious' => 5, 'moderate' => 2, 'minor' => 1];

    public function __construct(private Scanner $scanner, private PageDiscovery $discovery) {}

    public function run(Scan $scan): void
    {
        $site = $scan->site;
        $scan->update(['status' => Scan::RUNNING, 'started_at' => now(), 'error' => null]);

        try {
            $urls = $this->discovery->discover($site);
            if ($urls === []) {
                throw new \RuntimeException('No pages could be found to scan. Check that the site URL is reachable.');
            }
            $scan->update(['pages_total' => count($urls)]);

            $okPageIds = [];
            $newIssueIds = [];
            foreach (array_chunk($urls, max(1, (int) config('curbcut.batch_size'))) as $batch) {
                $this->scanner->scan($batch, function (array $result) use ($scan, $site, &$okPageIds, &$newIssueIds) {
                    $outcome = $this->recordPage($scan, $site, $result);
                    if ($outcome['ok']) {
                        $okPageIds[] = $outcome['page_id'];
                    }
                    array_push($newIssueIds, ...$outcome['new_issue_ids']);
                });
            }

            $this->finish($scan, $site, $okPageIds, $newIssueIds);
        } catch (Throwable $e) {
            $scan->update(['status' => Scan::FAILED, 'error' => mb_substr($e->getMessage(), 0, 2000), 'finished_at' => now()]);
            report($e);
        }
    }

    /** @return array{ok: bool, page_id: int, new_issue_ids: list<int>} */
    private function recordPage(Scan $scan, Site $site, array $result): array
    {
        $url = UrlTools::normalize((string) $result['url']) ?? (string) $result['url'];
        $page = Page::firstOrCreate(
            ['site_id' => $site->id, 'url_hash' => Page::hashUrl($url)],
            ['url' => $url],
        );

        $httpStatus = $result['httpStatus'] ?? null;
        $ok = ($result['status'] ?? '') === 'ok' && ($httpStatus === null || $httpStatus < 400);
        $error = $ok ? null : ($result['error'] ?? ($httpStatus ? "The page returned HTTP {$httpStatus}." : 'The page could not be scanned.'));

        if (! empty($result['title'])) {
            $page->update(['title' => mb_substr($result['title'], 0, 255)]);
        }

        $newIssueIds = [];
        $violationNodes = 0;
        if ($ok) {
            DB::transaction(function () use ($scan, $site, $page, $result, &$newIssueIds, &$violationNodes) {
                foreach ($result['violations'] ?? [] as $violation) {
                    foreach ($violation['nodes'] ?? [] as $node) {
                        $violationNodes++;
                        $fingerprint = Issue::fingerprintFor($site->id, $page->url, $violation['id'], (string) $node['target']);
                        $issue = Issue::firstOrNew(['site_id' => $site->id, 'fingerprint' => $fingerprint]);
                        $isNew = ! $issue->exists || $issue->status === 'fixed';
                        // A dismissed issue (false positive, won't fix) stays dismissed while it is still there.
                        $status = $issue->exists && $issue->status === 'dismissed' ? 'dismissed' : 'open';

                        $issue->fill([
                            'page_id' => $page->id,
                            'rule_id' => $violation['id'],
                            'impact' => in_array($violation['impact'] ?? null, Issue::IMPACTS, true) ? $violation['impact'] : 'minor',
                            'wcag' => array_values(array_filter($violation['tags'] ?? [], fn ($t) => str_starts_with($t, 'wcag'))),
                            'help' => mb_substr((string) ($violation['help'] ?? $violation['id']), 0, 255),
                            'description' => $violation['description'] ?? null,
                            'help_url' => $violation['helpUrl'] ?? null,
                            'target' => (string) $node['target'],
                            'html' => $node['html'] ?? null,
                            'failure_summary' => $node['failureSummary'] ?? null,
                            'status' => $status,
                            'last_seen_scan_id' => $scan->id,
                            'fixed_in_scan_id' => null,
                        ]);
                        if ($isNew) {
                            $issue->first_seen_scan_id = $scan->id;
                        }
                        $issue->save();
                        if ($isNew) {
                            $newIssueIds[] = $issue->id;
                        }
                    }
                }
            });
        }

        ScanPage::updateOrCreate(
            ['scan_id' => $scan->id, 'page_id' => $page->id],
            [
                'status' => $ok ? 'ok' : 'failed',
                'http_status' => $httpStatus,
                'violations' => $violationNodes,
                'passes' => (int) ($result['passes'] ?? 0),
                'duration_ms' => $result['durationMs'] ?? null,
                'error' => $error ? mb_substr($error, 0, 2000) : null,
            ],
        );

        $scan->increment($ok ? 'pages_scanned' : 'pages_failed');

        return ['ok' => $ok, 'page_id' => $page->id, 'new_issue_ids' => $newIssueIds];
    }

    /**
     * @param  list<int>  $okPageIds
     * @param  list<int>  $newIssueIds
     */
    private function finish(Scan $scan, Site $site, array $okPageIds, array $newIssueIds): void
    {
        // Open or dismissed issues on pages that scanned cleanly this time, but were not seen again, are fixed.
        // Pages that failed to load keep their issues as they are: we simply don't know yet.
        $fixed = $okPageIds === [] ? 0 : Issue::where('site_id', $site->id)
            ->whereIn('status', ['open', 'dismissed'])
            ->whereIn('page_id', $okPageIds)
            ->where(fn ($q) => $q->whereNull('last_seen_scan_id')->orWhere('last_seen_scan_id', '!=', $scan->id))
            ->update(['status' => 'fixed', 'fixed_in_scan_id' => $scan->id, 'updated_at' => now()]);

        $open = Issue::where('site_id', $site->id)->where('status', 'open');
        $byImpact = (clone $open)->selectRaw('impact, count(*) as total')->groupBy('impact')->pluck('total', 'impact');

        $scan->update([
            'status' => $scan->pages_scanned > 0 ? Scan::COMPLETED : Scan::FAILED,
            'error' => $scan->pages_scanned > 0 ? null : 'None of the pages could be scanned.',
            'issues_open' => (clone $open)->count(),
            'issues_new' => count(array_unique($newIssueIds)),
            'issues_fixed' => $fixed,
            'critical' => (int) ($byImpact['critical'] ?? 0),
            'serious' => (int) ($byImpact['serious'] ?? 0),
            'moderate' => (int) ($byImpact['moderate'] ?? 0),
            'minor' => (int) ($byImpact['minor'] ?? 0),
            'score' => $this->score($site, $okPageIds),
            'finished_at' => now(),
        ]);
        $site->update(['last_scanned_at' => now()]);
    }

    /**
     * Each scanned page starts at 100 and loses points per open issue
     * (critical 10, serious 5, moderate 2, minor 1), floored at 0.
     * The site score is the average over pages that scanned successfully.
     *
     * @param  list<int>  $pageIds
     */
    public function score(Site $site, array $pageIds): ?int
    {
        $pageIds = array_values(array_unique($pageIds));
        if ($pageIds === []) {
            return null;
        }

        $penalties = Issue::where('site_id', $site->id)->where('status', 'open')->whereIn('page_id', $pageIds)
            ->get(['page_id', 'impact'])
            ->groupBy('page_id')
            ->map(fn ($issues) => $issues->sum(fn ($i) => self::IMPACT_WEIGHTS[$i->impact] ?? 1));

        $total = 0;
        foreach ($pageIds as $id) {
            $total += max(0, 100 - (int) ($penalties[$id] ?? 0));
        }

        return (int) round($total / count($pageIds));
    }
}
