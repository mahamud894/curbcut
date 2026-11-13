<?php

namespace App\Checks;

use App\Models\Check;
use App\Models\Issue;
use App\Scanning\Scanner;
use App\Scanning\UrlTools;
use Throwable;

/**
 * Scans the same pages on another copy of a site (a pull request preview, staging)
 * and sorts every problem found into new, already known or dismissed. The site's
 * own issues and score are never changed by a check.
 */
class CheckRunner
{
    public function __construct(private Scanner $scanner) {}

    public function run(Check $check): void
    {
        $check->update(['status' => Check::RUNNING, 'started_at' => now(), 'error' => null]);

        try {
            $this->scan($check);
        } catch (Throwable $e) {
            $check->update(['status' => Check::FAILED, 'error' => mb_substr($e->getMessage(), 0, 2000), 'finished_at' => now()]);
        }
    }

    /** The path and query of a URL, used to match pages across hosts. */
    public static function pathOf(string $url): string
    {
        $parts = parse_url($url);

        return ($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : '');
    }

    public static function keyFor(string $path, string $ruleId, string $target): string
    {
        return sha1($path.'|'.$ruleId.'|'.$target);
    }

    private function scan(Check $check): void
    {
        $site = $check->site;
        $base = rtrim($check->base_url, '/');

        // What the site already knows about, keyed by path + rule + element.
        $known = [];
        $site->issues()->whereIn('status', ['open', 'dismissed'])->with('page:id,url')->get()
            ->each(function (Issue $issue) use (&$known) {
                $known[self::keyFor(self::pathOf($issue->page->url), $issue->rule_id, $issue->target)] = $issue;
            });

        $urls = [];
        foreach ($check->paths as $path) {
            $url = UrlTools::normalize($base.$path);
            if ($url !== null && UrlTools::isAllowedTarget($url)) {
                $urls[$url] = $path;
            }
        }

        $pages = [];
        $findings = [];
        $seen = [];
        $okPaths = [];

        $this->scanner->scan(array_keys($urls), function (array $result) use (&$pages, &$findings, &$seen, &$okPaths, $urls, $known, $check) {
            $url = UrlTools::normalize((string) $result['url']) ?? (string) $result['url'];
            $path = $urls[$url] ?? self::pathOf($url);
            $httpStatus = $result['httpStatus'] ?? null;
            $ok = ($result['status'] ?? '') === 'ok' && ($httpStatus === null || $httpStatus < 400);

            $pages[] = [
                'path' => $path,
                'url' => $url,
                'status' => $ok ? 'ok' : 'failed',
                'http_status' => $httpStatus,
                'error' => $ok ? null : ($result['error'] ?? ($httpStatus ? "The page returned HTTP {$httpStatus}." : 'The page could not be scanned.')),
            ];
            $check->increment($ok ? 'pages_scanned' : 'pages_failed');
            if (! $ok) {
                return;
            }
            $okPaths[$path] = true;

            foreach ($result['violations'] ?? [] as $violation) {
                foreach ($violation['nodes'] ?? [] as $node) {
                    $target = (string) $node['target'];
                    $key = self::keyFor($path, $violation['id'], $target);
                    $seen[$key] = true;
                    $existing = $known[$key] ?? null;

                    $findings[] = [
                        'status' => $existing === null ? 'new' : ($existing->status === 'dismissed' ? 'dismissed' : 'existing'),
                        'issue_id' => $existing?->id,
                        'path' => $path,
                        'url' => $url,
                        'rule_id' => $violation['id'],
                        'impact' => in_array($violation['impact'] ?? null, Issue::IMPACTS, true) ? $violation['impact'] : 'minor',
                        'help' => mb_substr((string) ($violation['help'] ?? $violation['id']), 0, 255),
                        'help_url' => $violation['helpUrl'] ?? null,
                        'wcag' => array_values(array_filter($violation['tags'] ?? [], fn ($t) => str_starts_with($t, 'wcag'))),
                        'target' => $target,
                        'html' => isset($node['html']) ? mb_substr($node['html'], 0, 500) : null,
                        'failure_summary' => $node['failureSummary'] ?? null,
                    ];
                }
            }
        });

        // Known open issues on pages that loaded fine but where the exact element was not found again.
        $unseen = collect($known)
            ->filter(fn (Issue $issue, string $key) => $issue->status === 'open' && ! isset($seen[$key]) && isset($okPaths[self::pathOf($issue->page->url)]));

        // Selectors shift when markup changes around an element (button:nth-child(3) becomes
        // button:nth-child(4)). Pair a "new" finding with an unseen known issue of the same rule
        // on the same page, preferring identical HTML, so a moved element is neither new nor fixed.
        foreach ([true, false] as $sameHtmlOnly) {
            foreach ($findings as $i => $finding) {
                if ($finding['status'] !== 'new') {
                    continue;
                }
                $match = $unseen->first(fn (Issue $issue) => $issue->rule_id === $finding['rule_id']
                    && self::pathOf($issue->page->url) === $finding['path']
                    && (! $sameHtmlOnly || trim((string) $issue->html) === trim((string) $finding['html'])));
                if ($match) {
                    $findings[$i]['status'] = 'existing';
                    $findings[$i]['issue_id'] = $match->id;
                    $unseen = $unseen->reject(fn (Issue $issue) => $issue->id === $match->id);
                }
            }
        }

        $fixed = $unseen
            ->map(fn (Issue $issue) => [
                'issue_id' => $issue->id,
                'path' => self::pathOf($issue->page->url),
                'rule_id' => $issue->rule_id,
                'impact' => $issue->impact,
                'help' => $issue->help,
                'target' => $issue->target,
            ])->values()->all();

        $new = collect($findings)->where('status', 'new');
        $impactOrder = array_flip(Issue::IMPACTS);
        usort($findings, fn ($a, $b) => [$a['status'] !== 'new', $impactOrder[$a['impact']], $a['path']] <=> [$b['status'] !== 'new', $impactOrder[$b['impact']], $b['path']]);

        $check->update([
            'status' => $check->fresh()->pages_scanned > 0 ? Check::COMPLETED : Check::FAILED,
            'error' => $check->fresh()->pages_scanned > 0 ? null : 'None of the pages could be scanned. Is the preview address reachable from the Curbcut server?',
            'issues_new' => $new->count(),
            'issues_existing' => collect($findings)->where('status', '!=', 'new')->count(),
            'issues_fixed' => count($fixed),
            'new_by_impact' => collect(Issue::IMPACTS)->mapWithKeys(fn ($i) => [$i => $new->where('impact', $i)->count()])->all(),
            'results' => ['pages' => $pages, 'issues' => $findings, 'fixed' => $fixed],
            'finished_at' => now(),
        ]);
    }
}
