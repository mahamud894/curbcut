<?php

namespace App\Http\Controllers;

use App\Actions\StartScan;
use App\Compliance\Wcag;
use App\Http\Requests\SiteRequest;
use App\Models\Issue;
use App\Models\Scan;
use App\Models\Site;
use App\Scanning\UrlTools;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SiteController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $workspace = $user->workspace();

        $sites = Site::query()
            ->where(fn ($q) => $q->where('organization_id', $workspace->id)
                // Guest access to single sites in workspaces the user doesn't belong to.
                ->orWhere(fn ($g) => $g->whereHas('members', fn ($m) => $m->whereKey($user->id))
                    ->whereNotIn('organization_id', $user->organizations()->select('organizations.id'))))
            ->with(['latestScan', 'latestCompletedScan', 'organization:id,name'])
            ->withCount(['issues as open_issues_count' => fn ($q) => $q->where('status', 'open')])
            ->orderBy('name')
            ->get()
            ->map(fn (Site $site) => self::summary($site) + [
                'shared' => $site->organization_id !== $workspace->id,
                'workspace_name' => $site->organization?->name,
            ]);

        return Inertia::render('sites/index', [
            'sites' => $sites,
            'canAddSites' => true,
            'totals' => [
                'sites' => $sites->count(),
                'open_issues' => $sites->sum('open_issues_count'),
                'critical' => $sites->sum(fn ($s) => $s['latest_completed_scan']['critical'] ?? 0),
                'average_score' => ($scores = $sites->pluck('latest_completed_scan.score')->filter(fn ($v) => $v !== null))->isNotEmpty()
                    ? (int) round($scores->avg()) : null,
            ],
        ]);
    }

    public function store(SiteRequest $request, StartScan $start): RedirectResponse
    {
        $data = $request->validated();
        $data['url'] = UrlTools::normalize($data['url']);
        $site = $request->user()->sites()->create($data + ['organization_id' => $request->user()->workspace()->id]);

        if ($request->boolean('scan_now', true)) {
            $start($site);
        }

        return to_route('sites.show', $site)->with('success', 'Site added. The first scan has started.');
    }

    public function show(Site $site): Response
    {
        $this->authorize('view', $site);
        $site->load(['latestScan', 'latestCompletedScan']);

        $history = $site->scans()->where('status', Scan::COMPLETED)->latest('id')->limit(12)->get()
            ->reverse()->values()
            ->map(fn (Scan $scan) => [
                'id' => $scan->id,
                'date' => $scan->finished_at?->toDateString(),
                'score' => $scan->score,
                'issues_open' => $scan->issues_open,
            ]);

        $topRules = $site->issues()->where('status', 'open')
            ->select('rule_id', 'help', 'impact', DB::raw('count(*) as total'), DB::raw('count(distinct page_id) as pages'))
            ->groupBy('rule_id', 'help', 'impact')
            ->orderByDesc('total')->limit(6)->get();

        return Inertia::render('sites/show', [
            'site' => self::summary($site->loadCount(['issues as open_issues_count' => fn ($q) => $q->where('status', 'open')])),
            'history' => $history,
            'topRules' => $topRules,
            'recentScans' => $site->scans()->latest('id')->limit(8)->get()->map(fn (Scan $s) => self::scanSummary($s)),
            'activity' => $site->activities()->with('user:id,name')->latest('id')->limit(10)->get()
                ->map(fn ($a) => ['id' => $a->id, 'action' => $a->action, 'properties' => $a->properties, 'user' => $a->user?->name, 'subject_id' => $a->subject_id, 'created_at' => $a->created_at?->toIso8601String()]),
            'myOpenIssues' => $site->issues()->where('status', 'open')->where('assignee_id', request()->user()->id)->count(),
        ]);
    }

    public function issues(Request $request, Site $site): Response
    {
        $this->authorize('view', $site);
        $filters = $request->validate([
            'status' => ['nullable', 'in:open,fixed,dismissed'],
            'assignee' => ['nullable', 'string', 'max:20'],
            'criterion' => ['nullable', 'regex:/^\d\.\d\.\d{1,2}$/'],
            'impact' => ['nullable', 'in:'.implode(',', Issue::IMPACTS)],
            'rule' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:200'],
        ]);
        $status = $filters['status'] ?? 'open';

        $base = $site->issues()->where('status', $status)
            ->when($filters['impact'] ?? null, fn ($q, $v) => $q->where('impact', $v))
            ->when($filters['page'] ?? null, fn ($q, $v) => $q->where('page_id', $v))
            ->when($filters['criterion'] ?? null, fn ($q, $v) => $q->whereJsonContains('wcag', 'wcag'.str_replace('.', '', $v)))
            ->when($filters['assignee'] ?? null, fn ($q, $v) => match ($v) {
                'me' => $q->where('assignee_id', $request->user()->id),
                'none' => $q->whereNull('assignee_id'),
                'overdue' => $q->whereNotNull('due_on')->whereDate('due_on', '<', today()),
                default => $q->where('assignee_id', (int) $v),
            })
            ->when($filters['search'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w->where('help', 'like', "%{$v}%")->orWhere('target', 'like', "%{$v}%")->orWhere('rule_id', 'like', "%{$v}%")));

        $rules = (clone $base)
            ->select('rule_id', 'help', 'impact', DB::raw('count(*) as total'), DB::raw('count(distinct page_id) as pages'))
            ->groupBy('rule_id', 'help', 'impact')->get()
            ->sortBy([fn ($a, $b) => array_search($a->impact, Issue::IMPACTS) <=> array_search($b->impact, Issue::IMPACTS), fn ($a, $b) => $b->total <=> $a->total])
            ->values();

        $issues = (clone $base)
            ->when($filters['rule'] ?? null, fn ($q, $v) => $q->where('rule_id', $v))
            ->with(['page:id,url,title', 'assignee:id,name'])
            ->orderByRaw("case impact when 'critical' then 0 when 'serious' then 1 when 'moderate' then 2 else 3 end")
            ->latest('id')
            ->paginate(25)->withQueryString()
            ->through(fn (Issue $i) => self::issueRow($i) + [
                'assignee' => $i->assignee?->only(['id', 'name']),
                'due_on' => $i->due_on?->toDateString(),
                'overdue' => $i->isOverdue(),
            ]);

        return Inertia::render('sites/issues', [
            'site' => self::summary($site->load(['latestScan', 'latestCompletedScan'])),
            'rules' => $rules,
            'team' => $site->team()->map->only(['id', 'name']),
            'dismissReasons' => Issue::DISMISS_REASONS,
            'issues' => $issues,
            'filters' => ['status' => $status] + $filters,
            'pages' => $site->pages()->orderBy('url')->get(['id', 'url', 'title']),
        ]);
    }

    public function pages(Site $site): Response
    {
        $this->authorize('view', $site);
        $site->load(['latestScan', 'latestCompletedScan']);
        $lastScanId = $site->latestCompletedScan?->id;

        $pages = $site->pages()
            ->withCount(['issues as open_issues' => fn ($q) => $q->where('status', 'open')])
            ->withCount(['issues as critical' => fn ($q) => $q->where('status', 'open')->where('impact', 'critical')])
            ->with(['results' => fn ($q) => $q->where('scan_id', $lastScanId)])
            ->orderByDesc('open_issues')->orderBy('url')
            ->get()
            ->map(fn ($page) => [
                'id' => $page->id,
                'url' => $page->url,
                'title' => $page->title,
                'open_issues' => $page->open_issues,
                'critical' => $page->critical,
                'last_result' => $page->results->first()?->only(['status', 'http_status', 'error', 'duration_ms']),
            ]);

        return Inertia::render('sites/pages', ['site' => self::summary($site), 'pages' => $pages]);
    }

    public function scan(Site $site, Scan $scan): Response
    {
        $this->authorize('view', $site);
        abort_unless($scan->site_id === $site->id, 404);
        $site->load(['latestScan', 'latestCompletedScan']);

        $issueRow = fn (Issue $i) => ['id' => $i->id, 'rule_id' => $i->rule_id, 'impact' => $i->impact, 'help' => $i->help, 'target' => $i->target, 'page_url' => $i->page->url];

        return Inertia::render('sites/scan', [
            'site' => self::summary($site),
            'scan' => self::scanSummary($scan),
            'newIssues' => $site->issues()->where('first_seen_scan_id', $scan->id)->where('status', 'open')->with('page:id,url')->limit(200)->get()->map($issueRow),
            'fixedIssues' => $site->issues()->where('fixed_in_scan_id', $scan->id)->with('page:id,url')->limit(200)->get()->map($issueRow),
            'failedPages' => $scan->results()->where('status', 'failed')->with('page:id,url')->get()->map(fn ($r) => ['url' => $r->page->url, 'error' => $r->error, 'http_status' => $r->http_status]),
        ]);
    }

    public function edit(Site $site): Response
    {
        $this->authorize('update', $site);

        return Inertia::render('sites/settings', [
            'site' => self::summary($site->load(['latestScan', 'latestCompletedScan', 'organization', 'members'])),
            'siteWorkspace' => ['name' => $site->organization?->name, 'people' => $site->organization?->users()->count() ?? 0, 'id' => $site->organization_id],
            'members' => $site->members->map->only(['id', 'name', 'email']),
            'canManageMembers' => request()->user()->can('manageMembers', $site),
        ]);
    }

    public function update(SiteRequest $request, Site $site): RedirectResponse
    {
        $data = $request->validated();
        $data['url'] = UrlTools::normalize($data['url']);
        $site->update($data);

        return back()->with('success', 'Settings saved.');
    }

    public function destroy(Site $site): RedirectResponse
    {
        $this->authorize('delete', $site);
        $site->delete();

        return to_route('sites.index')->with('success', 'Site deleted.');
    }

    public function startScan(Site $site, StartScan $start): RedirectResponse
    {
        $this->authorize('update', $site);

        return $start($site)
            ? back()->with('success', 'Scan started.')
            : back()->with('error', 'A scan is already running for this site.');
    }

    public static function summary(Site $site): array
    {
        return [
            'id' => $site->id,
            'name' => $site->name,
            'url' => $site->url,
            'sitemap_url' => $site->sitemap_url,
            'max_pages' => $site->max_pages,
            'scan_frequency' => $site->scan_frequency,
            'last_scanned_at' => $site->last_scanned_at?->toIso8601String(),
            'open_issues_count' => $site->open_issues_count ?? $site->issues()->where('status', 'open')->count(),
            'latest_scan' => $site->latestScan ? self::scanSummary($site->latestScan) : null,
            'latest_completed_scan' => $site->latestCompletedScan ? self::scanSummary($site->latestCompletedScan) : null,
        ];
    }

    public static function scanSummary(Scan $scan): array
    {
        return $scan->only([
            'id', 'status', 'trigger', 'pages_total', 'pages_scanned', 'pages_failed', 'issues_open', 'issues_new',
            'issues_fixed', 'critical', 'serious', 'moderate', 'minor', 'score', 'error',
        ]) + [
            'started_at' => $scan->started_at?->toIso8601String(),
            'finished_at' => $scan->finished_at?->toIso8601String(),
            'created_at' => $scan->created_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public static function issueRow(Issue $i): array
    {
        return [
            'id' => $i->id,
            'rule_id' => $i->rule_id,
            'impact' => $i->impact,
            'help' => $i->help,
            'description' => $i->description,
            'help_url' => $i->help_url,
            'wcag' => self::wcagCriteria($i->wcag),
            'target' => $i->target,
            'html' => $i->html,
            'failure_summary' => $i->failure_summary,
            'status' => $i->status,
            'page' => ['id' => $i->page->id, 'url' => $i->page->url, 'title' => $i->page->title],
            'first_seen_scan_id' => $i->first_seen_scan_id,
            'fixed_in_scan_id' => $i->fixed_in_scan_id,
        ];
    }

    /** Turn axe tags like "wcag143" into readable success criteria like "1.4.3". @return list<string> */
    private static function wcagCriteria(array $tags): array
    {
        return array_values(array_unique(array_filter(array_map(fn ($t) => Wcag::fromAxeTag($t), $tags))));
    }
}
