<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\StartScan;
use App\Checks\CheckRunner;
use App\Http\Controllers\Controller;
use App\Http\Resources\CheckResource;
use App\Http\Resources\IssueResource;
use App\Http\Resources\ScanResource;
use App\Http\Resources\SiteResource;
use App\Jobs\RunCheck;
use App\Models\Check;
use App\Models\Issue;
use App\Models\Scan;
use App\Models\Site;
use App\Rules\ScannableUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Curbcut REST API, version 1. Authenticate with a personal access token:
 *   Authorization: Bearer <token>
 * Tokens with the "read" ability can read everything the user can see;
 * "scan" also allows starting scans and checks.
 */
class ApiController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json(['data' => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'token' => ['name' => $user->currentAccessToken()?->name, 'abilities' => $user->currentAccessToken()?->abilities],
            'workspaces' => $user->organizations()->orderBy('name')->get()->map(fn ($o) => ['id' => $o->id, 'name' => $o->name, 'role' => $o->pivot->role]),
        ]]);
    }

    public function sites(Request $request): AnonymousResourceCollection
    {
        $this->requireAbility($request, 'read');
        $filters = $request->validate(['workspace' => ['nullable', 'integer']]);

        $sites = $request->user()->accessibleSites()
            ->when($filters['workspace'] ?? null, fn ($q, $id) => $q->where('organization_id', $id))
            ->with(['organization', 'latestCompletedScan'])
            ->withCount(['issues as open_issues_count' => fn ($q) => $q->where('status', 'open')])
            ->orderBy('name')
            ->paginate(50);

        return SiteResource::collection($sites);
    }

    public function site(Request $request, Site $site): SiteResource
    {
        $this->requireAbility($request, 'read');
        $this->authorize('view', $site);

        return new SiteResource($site->load(['organization', 'latestCompletedScan'])
            ->loadCount(['issues as open_issues_count' => fn ($q) => $q->where('status', 'open')]));
    }

    public function scans(Request $request, Site $site): AnonymousResourceCollection
    {
        $this->requireAbility($request, 'read');
        $this->authorize('view', $site);

        return ScanResource::collection($site->scans()->latest('id')->paginate(25));
    }

    public function scan(Request $request, Site $site, Scan $scan): ScanResource
    {
        $this->requireAbility($request, 'read');
        $this->authorize('view', $site);

        return new ScanResource($scan);
    }

    public function startScan(Request $request, Site $site, StartScan $start): JsonResponse
    {
        $this->requireAbility($request, 'scan');
        $this->authorize('update', $site);

        $scan = $start($site, 'api');
        if (! $scan) {
            return response()->json([
                'message' => 'A scan of this site is already queued or running.',
                'data' => new ScanResource($site->latestScan),
            ], 409);
        }

        return (new ScanResource($scan))->response()->setStatusCode(202);
    }

    public function issues(Request $request, Site $site): AnonymousResourceCollection
    {
        $this->requireAbility($request, 'read');
        $this->authorize('view', $site);
        $filters = $request->validate([
            'status' => ['nullable', 'in:open,fixed,dismissed'],
            'impact' => ['nullable', 'in:'.implode(',', Issue::IMPACTS)],
            'rule' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $impactOrder = "case impact when 'critical' then 0 when 'serious' then 1 when 'moderate' then 2 else 3 end";
        $issues = $site->issues()
            ->where('status', $filters['status'] ?? 'open')
            ->when($filters['impact'] ?? null, fn ($q, $v) => $q->where('impact', $v))
            ->when($filters['rule'] ?? null, fn ($q, $v) => $q->where('rule_id', $v))
            ->when($filters['page'] ?? null, fn ($q, $v) => $q->where('page_id', $v))
            ->with(['page', 'assignee'])
            ->orderByRaw($impactOrder)->orderBy('id')
            ->paginate($filters['per_page'] ?? 50)
            ->withQueryString();

        return IssueResource::collection($issues);
    }

    public function issue(Request $request, Site $site, Issue $issue): IssueResource
    {
        $this->requireAbility($request, 'read');
        $this->authorize('view', $site);

        return new IssueResource($issue->load(['page', 'assignee']));
    }

    public function pages(Request $request, Site $site): JsonResponse
    {
        $this->requireAbility($request, 'read');
        $this->authorize('view', $site);

        $pages = $site->pages()
            ->withCount(['issues as open_issues_count' => fn ($q) => $q->where('status', 'open')])
            ->orderBy('url')->paginate(100);

        return response()->json([
            'data' => $pages->getCollection()->map(fn ($p) => [
                'id' => $p->id, 'url' => $p->url, 'title' => $p->title, 'open_issues' => (int) $p->open_issues_count,
            ]),
            'meta' => ['current_page' => $pages->currentPage(), 'last_page' => $pages->lastPage(), 'total' => $pages->total()],
        ]);
    }

    /**
     * Start a check: scan another copy of the site (a pull request preview, staging)
     * and compare it with the site's known issues. Nothing on the site itself changes.
     */
    public function startCheck(Request $request, Site $site): JsonResponse
    {
        $this->requireAbility($request, 'scan');
        $this->authorize('update', $site);
        $data = $request->validate([
            'base_url' => ['required', 'string', 'max:2000', new ScannableUrl],
            'paths' => ['nullable', 'array', 'max:'.config('curbcut.max_pages_limit')],
            'paths.*' => ['string', 'max:2000', 'regex:/^\//'],
            'max_pages' => ['nullable', 'integer', 'min:1', 'max:'.config('curbcut.max_pages_limit')],
            'ref' => ['nullable', 'string', 'max:255'],
        ]);

        $paths = $data['paths'] ?? null;
        if (! $paths) {
            // By default, check the pages Curbcut already knows, most problematic first.
            $paths = $site->pages()
                ->withCount(['issues as open_issues_count' => fn ($q) => $q->where('status', 'open')])
                ->orderByDesc('open_issues_count')->orderBy('id')
                ->limit($data['max_pages'] ?? min($site->max_pages, 25))
                ->pluck('url')->map(fn ($url) => CheckRunner::pathOf($url))->all();
        }
        $paths = array_values(array_unique($paths ?: ['/']));

        $check = $site->checks()->create([
            'user_id' => $request->user()->id,
            'base_url' => $data['base_url'],
            'ref' => $data['ref'] ?? null,
            'paths' => $paths,
        ]);
        RunCheck::dispatch($check);

        return (new CheckResource($check->fresh()))->response()->setStatusCode(202);
    }

    public function check(Request $request, Site $site, Check $check): CheckResource
    {
        $this->requireAbility($request, 'read');
        $this->authorize('view', $site);

        return new CheckResource($check);
    }

    private function requireAbility(Request $request, string $ability): void
    {
        abort_unless($request->user()->tokenCan($ability), 403, "This token does not have the \"{$ability}\" ability.");
    }
}
