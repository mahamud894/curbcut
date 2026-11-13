@php
    $impactColors = ['critical' => '#b91c1c', 'serious' => '#c2410c', 'moderate' => '#a16207', 'minor' => '#475569'];
    $statusWords = ['pass' => 'Pass', 'fail' => 'Fail', 'not_applicable' => 'Not applicable', 'not_tested' => 'Not tested'];
    $scoreLabel = fn ($s) => $s === null ? 'Not scanned' : ($s >= 90 ? 'Good' : ($s >= 70 ? 'Needs work' : 'Poor'));
    $counts = $assessment['counts'];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Accessibility audit report: {{ $site->name }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; color: #111827; font-size: 10.5pt; line-height: 1.45; margin: 0; }
        h1 { font-size: 26pt; margin: 0 0 6pt; line-height: 1.15; }
        h2 { font-size: 15pt; margin: 22pt 0 8pt; padding-bottom: 4pt; border-bottom: 1.5pt solid #111827; break-after: avoid; }
        h3 { font-size: 11.5pt; margin: 14pt 0 4pt; break-after: avoid; }
        p { margin: 0 0 6pt; }
        a { color: #1d4ed8; }
        .muted { color: #4b5563; }
        .small { font-size: 9pt; }
        .cover { min-height: 245mm; display: flex; flex-direction: column; justify-content: space-between; break-after: page; }
        .brand { font-weight: bold; letter-spacing: .5pt; text-transform: uppercase; font-size: 9pt; color: #374151; }
        .cover-meta { border-top: 1pt solid #d1d5db; padding-top: 10pt; }
        .kpis { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8pt; margin: 10pt 0; }
        .kpi { border: 1pt solid #d1d5db; border-radius: 6pt; padding: 8pt 10pt; }
        .kpi .label { font-size: 8.5pt; color: #4b5563; text-transform: uppercase; letter-spacing: .3pt; }
        .kpi .value { font-size: 20pt; font-weight: bold; }
        .badge { display: inline-block; border-radius: 99pt; padding: 1pt 7pt; font-size: 8.5pt; font-weight: bold; color: #fff; }
        table { width: 100%; border-collapse: collapse; margin: 6pt 0 10pt; font-size: 9.5pt; }
        th, td { text-align: left; padding: 4pt 6pt; border-bottom: .75pt solid #e5e7eb; vertical-align: top; }
        thead th { background: #f3f4f6; font-size: 8.5pt; text-transform: uppercase; letter-spacing: .2pt; }
        tr { break-inside: avoid; }
        .num { text-align: right; font-variant-numeric: tabular-nums; }
        code, .code { font-family: "Courier New", monospace; font-size: 8.5pt; word-break: break-all; }
        .rule { border: 1pt solid #e5e7eb; border-radius: 6pt; padding: 8pt 10pt; margin: 8pt 0; break-inside: avoid; }
        .callout { background: #f3f4f6; border-left: 3pt solid #111827; padding: 8pt 10pt; margin: 8pt 0; }
        .bar { display: flex; height: 8pt; border-radius: 4pt; overflow: hidden; background: #e5e7eb; margin: 4pt 0 6pt; }
        ul { margin: 4pt 0 8pt; padding-left: 14pt; }
        .page-break { break-before: page; }
    </style>
</head>
<body>
<main>
    <section class="cover" aria-label="Cover">
        <div>
            <p class="brand">Accessibility audit report</p>
            <h1>{{ $site->name }}</h1>
            <p class="muted">{{ $site->url }}</p>
        </div>
        <div>
            <div class="kpis">
                <div class="kpi"><div class="label">Score</div><div class="value">{{ $scan?->score ?? '–' }}<span class="small muted"> / 100</span></div><div class="small">{{ $scoreLabel($scan?->score) }}</div></div>
                <div class="kpi"><div class="label">Conformance</div><div class="value" style="font-size:13pt;padding-top:6pt">{{ $conformance_label }}</div><div class="small">WCAG 2.2 AA</div></div>
                <div class="kpi"><div class="label">Open issues</div><div class="value">{{ number_format($scan?->issues_open ?? 0) }}</div><div class="small">{{ $scan?->critical ?? 0 }} critical</div></div>
                <div class="kpi"><div class="label">Checklist</div><div class="value">{{ $counts['total'] - $counts['not_tested'] }}<span class="small muted"> / {{ $counts['total'] }}</span></div><div class="small">criteria reviewed</div></div>
            </div>
        </div>
        <div class="cover-meta small">
            @if ($workspace)<p><strong>Workspace:</strong> {{ $workspace }}</p>@endif
            <p><strong>Generated:</strong> {{ $generated_at->toDayDateTimeString() }} UTC by {{ $generated_by }}</p>
            <p><strong>Latest scan:</strong> {{ $scan ? $scan->finished_at?->toDayDateTimeString().' UTC, '.$scan->pages_scanned.' pages' : 'No completed scan' }}</p>
            @if ($statement_url)<p><strong>Public accessibility statement:</strong> <a href="{{ $statement_url }}">{{ $statement_url }}</a></p>@endif
            <p class="muted">Produced with Curbcut, open-source accessibility compliance software. Automated testing uses axe-core.</p>
        </div>
    </section>

    <section aria-labelledby="summary">
        <h2 id="summary">1. Summary</h2>
        @if (! $scan)
            <p>No scan has completed yet, so this report only covers the manual checklist.</p>
        @else
            <p>
                The latest automated scan checked <strong>{{ $scan->pages_scanned }}</strong> {{ Str::plural('page', $scan->pages_scanned) }}
                @if ($scan->pages_failed) ({{ $scan->pages_failed }} could not be loaded) @endif
                and found <strong>{{ number_format($scan->issues_open) }}</strong> open {{ Str::plural('issue', $scan->issues_open) }}:
                {{ $scan->critical }} critical, {{ $scan->serious }} serious, {{ $scan->moderate }} moderate and {{ $scan->minor }} minor.
                {{ $scan->issues_new }} {{ Str::plural('issue', $scan->issues_new) }} were new and {{ $fixed_recently }} were fixed compared with the previous scan.
            </p>
            <p>{{ $assigned }} open {{ Str::plural('issue', $assigned) }} {{ $assigned === 1 ? 'is' : 'are' }} assigned to someone{{ $overdue ? ", {$overdue} ".Str::plural('is', $overdue)." overdue" : '' }}. {{ $dismissed->count() }} {{ Str::plural('issue', $dismissed->count()) }} {{ $dismissed->count() === 1 ? 'was' : 'were' }} dismissed with a justification (see section 5).</p>
        @endif
        <p>
            Based on the checklist and the open automated issues, the site is <strong>{{ strtolower($conformance_label) }}</strong> with WCAG 2.2 level AA{{ $conformance_is_override ? ' (status set manually by the team)' : '' }}.
            @unless ($assessment['complete'])
                {{ $counts['not_tested'] }} of {{ $counts['total'] }} success criteria have not been reviewed manually yet, so this status is provisional.
            @endunless
        </p>
        <div class="callout small">
            <strong>About the score.</strong> Each scanned page starts at 100 and loses 10 points per critical, 5 per serious, 2 per moderate and 1 per minor open issue (minimum 0). The site score is the average across pages. Automated tools find only part of all accessibility barriers; the score is not a certificate of conformance.
        </div>

        @if ($history->count() > 1)
            <h3>Score history</h3>
            <table>
                <thead><tr><th scope="col">Scan date</th><th scope="col" class="num">Pages</th><th scope="col" class="num">Score</th><th scope="col" class="num">Open issues</th><th scope="col" class="num">New</th><th scope="col" class="num">Fixed</th></tr></thead>
                <tbody>
                @foreach ($history as $h)
                    <tr><td>{{ $h->finished_at?->toFormattedDateString() }}</td><td class="num">{{ $h->pages_scanned }}</td><td class="num">{{ $h->score ?? '–' }}</td><td class="num">{{ $h->issues_open }}</td><td class="num">{{ $h->issues_new }}</td><td class="num">{{ $h->issues_fixed }}</td></tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </section>

    <section aria-labelledby="checklist">
        <h2 id="checklist">2. WCAG 2.2 A and AA checklist</h2>
        <p>
            {{ $counts['pass'] }} passing, {{ $counts['fail'] }} failing, {{ $counts['not_applicable'] }} not applicable and {{ $counts['not_tested'] }} not yet reviewed.
            A criterion with open automated issues is treated as failing.
        </p>
        <table>
            <thead><tr><th scope="col">Criterion</th><th scope="col">Level</th><th scope="col">Result</th><th scope="col" class="num">Automated issues</th><th scope="col">Notes</th></tr></thead>
            <tbody>
            @foreach ($assessment['criteria'] as $c)
                @php $failing = $c['status'] === 'fail' || ($c['automated_issues'] > 0 && $c['status'] !== 'not_applicable'); @endphp
                <tr>
                    <td><strong>{{ $c['id'] }}</strong> {{ $c['title'] }}</td>
                    <td>{{ $c['level'] }}</td>
                    <td style="{{ $failing ? 'color:#b91c1c;font-weight:bold' : '' }}">{{ $failing && $c['status'] !== 'fail' ? 'Fail (automated)' : $statusWords[$c['status']] }}</td>
                    <td class="num">{{ $c['automated_issues'] ?: '' }}</td>
                    <td class="small">{{ $c['notes'] }}@if ($c['tested_by'])<br><span class="muted">{{ $c['tested_by'] }}, {{ \Illuminate\Support\Carbon::parse($c['tested_at'])->toFormattedDateString() }}</span>@endif</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </section>

    <section aria-labelledby="problems" class="page-break">
        <h2 id="problems">3. Open problems found by automated testing</h2>
        @if ($rules->isEmpty())
            <p>No open issues were found by automated testing.</p>
        @else
            <p>Problems are ordered by impact and then by how often they occur. Up to five examples are listed per problem; the full list is in Curbcut.</p>
            @foreach ($rules as $rule)
                <div class="rule">
                    <h3 style="margin-top:0">
                        <span class="badge" style="background: {{ $impactColors[$rule['impact']] ?? '#475569' }}">{{ ucfirst($rule['impact']) }}</span>
                        {{ $rule['help'] }}
                    </h3>
                    <p class="small muted">
                        {{ $rule['total'] }} {{ Str::plural('element', $rule['total']) }} on {{ $rule['pages'] }} {{ Str::plural('page', $rule['pages']) }}
                        · rule <code>{{ $rule['rule_id'] }}</code>
                        @if ($rule['criteria']) · WCAG {{ implode(', ', $rule['criteria']) }} @else · best practice @endif
                        @if ($rule['help_url']) · <a href="{{ $rule['help_url'] }}">how to fix</a> @endif
                    </p>
                    <table>
                        <thead><tr><th scope="col">Page</th><th scope="col">Element</th><th scope="col">Assignee</th></tr></thead>
                        <tbody>
                        @foreach ($rule['examples'] as $ex)
                            <tr><td class="small">{{ $ex['page'] }}</td><td class="code">{{ $ex['target'] }}</td><td class="small">{{ $ex['assignee'] ?? 'Unassigned' }}{{ $ex['due_on'] ? ', due '.$ex['due_on'] : '' }}</td></tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endforeach
        @endif
    </section>

    <section aria-labelledby="pages">
        <h2 id="pages">4. Pages with the most issues</h2>
        <p>{{ $page_count }} {{ Str::plural('page', $page_count) }} known for this site. The {{ min($page_count, 25) }} with the most open issues:</p>
        <table>
            <thead><tr><th scope="col">Page</th><th scope="col" class="num">Critical</th><th scope="col" class="num">Open issues</th></tr></thead>
            <tbody>
            @foreach ($pages as $page)
                <tr><td class="small">{{ $page->title ? $page->title.' — ' : '' }}{{ $page->url }}</td><td class="num">{{ $page->critical }}</td><td class="num">{{ $page->open_issues }}</td></tr>
            @endforeach
            </tbody>
        </table>
    </section>

    <section aria-labelledby="dismissed">
        <h2 id="dismissed">5. Dismissed issues</h2>
        @if ($dismissed->isEmpty())
            <p>No issues have been dismissed.</p>
        @else
            <p>These issues were reviewed and not fixed, with the reason given by the team. They do not count towards the score.</p>
            <table>
                <thead><tr><th scope="col">Problem</th><th scope="col">Reason</th><th scope="col">Justification</th><th scope="col">By</th></tr></thead>
                <tbody>
                @foreach ($dismissed as $issue)
                    <tr>
                        <td>{{ $issue->help }}<br><span class="code">{{ $issue->target }}</span><br><span class="small muted">{{ $issue->page->url }}</span></td>
                        <td>{{ \App\Models\Issue::DISMISS_REASONS[$issue->dismissed_reason] ?? $issue->dismissed_reason }}</td>
                        <td class="small">{{ $issue->dismissed_note }}</td>
                        <td class="small">{{ $issue->dismisser?->name }}<br>{{ $issue->dismissed_at?->toFormattedDateString() }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </section>

    <section aria-labelledby="trail">
        <h2 id="trail">6. Audit trail</h2>
        @if ($activity->isEmpty())
            <p>No changes have been recorded yet.</p>
        @else
            <p>The most recent {{ $activity->count() }} recorded changes.</p>
            <table>
                <thead><tr><th scope="col">Date (UTC)</th><th scope="col">Change</th></tr></thead>
                <tbody>
                @foreach ($activity as $a)
                    <tr><td class="small" style="white-space:nowrap">{{ $a->created_at?->format('Y-m-d H:i') }}</td><td>{{ $a->describe() }}</td></tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </section>
</main>
</body>
</html>
