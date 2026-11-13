@php
    $impactColors = ['critical' => '#b91c1c', 'serious' => '#c2410c', 'moderate' => '#a16207', 'minor' => '#475569'];
    $statusWords = ['pass' => tr('Pass'), 'fail' => tr('Fail'), 'not_applicable' => tr('Not applicable'), 'not_tested' => tr('Not tested')];
    $scoreLabel = fn ($s) => $s === null ? tr('Not scanned yet') : ($s >= 90 ? tr('Good') : ($s >= 70 ? tr('Needs work') : tr('Poor')));
    $counts = $assessment['counts'];
    $locale = app()->getLocale();
    $date = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->locale($locale)->isoFormat('LL') : '';
    $dateTime = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->locale($locale)->isoFormat('LLL') : '';
    $num = fn ($n) => class_exists(\NumberFormatter::class) ? (new \NumberFormatter($locale, \NumberFormatter::DECIMAL))->format($n) : (string) $n;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}">
<head>
    <meta charset="utf-8">
    <title>{{ tr('Accessibility audit report: {site}', ['site' => $site->name]) }}</title>
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
    <section class="cover" aria-label="{{ tr('Cover') }}">
        <div>
            <p class="brand">{{ tr('Accessibility audit report') }}</p>
            <h1>{{ $site->name }}</h1>
            <p class="muted">{{ $site->url }}</p>
        </div>
        <div>
            <div class="kpis">
                <div class="kpi"><div class="label">{{ tr('Score') }}</div><div class="value">{{ $scan?->score ?? '–' }}<span class="small muted"> / 100</span></div><div class="small">{{ $scoreLabel($scan?->score) }}</div></div>
                <div class="kpi"><div class="label">{{ tr('Conformance') }}</div><div class="value" style="font-size:13pt;padding-top:6pt">{{ tr($conformance_label) }}</div><div class="small">WCAG 2.2 AA</div></div>
                <div class="kpi"><div class="label">{{ tr('Open issues') }}</div><div class="value">{{ $num($scan?->issues_open ?? 0) }}</div><div class="small">{{ tr('{count} critical', ['count' => $scan?->critical ?? 0]) }}</div></div>
                <div class="kpi"><div class="label">{{ tr('Checklist') }}</div><div class="value">{{ $counts['total'] - $counts['not_tested'] }}<span class="small muted"> / {{ $counts['total'] }}</span></div><div class="small">{{ tr('criteria reviewed') }}</div></div>
            </div>
        </div>
        <div class="cover-meta small">
            @if ($workspace)<p><strong>{{ tr('Workspace:') }}</strong> {{ $workspace }}</p>@endif
            <p><strong>{{ tr('Generated:') }}</strong> {{ tr('{date} UTC by {person}', ['date' => $dateTime($generated_at), 'person' => $generated_by]) }}</p>
            <p><strong>{{ tr('Latest scan:') }}</strong> {!! e($scan ? tr('{date} UTC, {count, plural, one {# page} other {# pages}}', ['date' => $dateTime($scan->finished_at), 'count' => $scan->pages_scanned]) : tr('No completed scan yet')) !!}</p>
            @if ($statement_url)<p><strong>{{ tr('Public accessibility statement:') }}</strong> <a href="{{ $statement_url }}">{{ $statement_url }}</a></p>@endif
            <p class="muted">{{ tr('Produced with Curbcut, open-source accessibility compliance software. Automated testing uses axe-core.') }}</p>
        </div>
    </section>

    <section aria-labelledby="summary">
        <h2 id="summary">1. {{ tr('Summary') }}</h2>
        @if (! $scan)
            <p>{{ tr('No scan has completed yet, so this report only covers the manual checklist.') }}</p>
        @else
            <p>
                {!! e(tr('The latest automated scan checked {pages, plural, one {# page} other {# pages}} and found {issues, plural, one {# open issue} other {# open issues}}: {critical} critical, {serious} serious, {moderate} moderate and {minor} minor.', ['pages' => $scan->pages_scanned, 'issues' => $scan->issues_open, 'critical' => $scan->critical, 'serious' => $scan->serious, 'moderate' => $scan->moderate, 'minor' => $scan->minor])) !!}
                @if ($scan->pages_failed) {!! e(tr('{count, plural, one {# page} other {# pages}} could not be loaded.', ['count' => $scan->pages_failed])) !!} @endif
                {!! e(tr('Compared with the previous scan, {new, plural, one {# issue is} other {# issues are}} new and {fixed, plural, one {# was} other {# were}} fixed.', ['new' => $scan->issues_new, 'fixed' => $fixed_recently])) !!}
            </p>
            <p>
                {!! e(tr('{count, plural, one {# open issue is} other {# open issues are}} assigned to someone.', ['count' => $assigned])) !!}
                @if ($overdue) {!! e(tr('{count, plural, one {# is} other {# are}} overdue.', ['count' => $overdue])) !!} @endif
                {!! e(tr('{count, plural, one {# issue was} other {# issues were}} dismissed with a justification (see section 5).', ['count' => $dismissed->count()])) !!}
            </p>
        @endif
        <p>
            {{ tr('Conformance with WCAG 2.2 level AA, based on the checklist and the open automated issues:') }} <strong>{{ tr($conformance_label) }}</strong>{{ $conformance_is_override ? ' '.tr('(status set manually by the team)') : '' }}.
            @unless ($assessment['complete'])
                {{ tr('{count} of {total} success criteria have not been reviewed manually yet, so this status is provisional.', ['count' => $counts['not_tested'], 'total' => $counts['total']]) }}
            @endunless
        </p>
        <div class="callout small">
            <strong>{{ tr('About the score.') }}</strong> {{ tr('Each scanned page starts at 100 and loses 10 points per critical, 5 per serious, 2 per moderate and 1 per minor open issue (minimum 0). The site score is the average across pages. Automated tools find only part of all accessibility barriers; the score is not a certificate of conformance.') }}
        </div>

        @if ($history->count() > 1)
            <h3>{{ tr('Score history') }}</h3>
            <table>
                <thead><tr><th scope="col">{{ tr('Scan date') }}</th><th scope="col" class="num">{{ tr('Pages') }}</th><th scope="col" class="num">{{ tr('Score') }}</th><th scope="col" class="num">{{ tr('Open issues') }}</th><th scope="col" class="num">{{ tr('New') }}</th><th scope="col" class="num">{{ tr('Fixed') }}</th></tr></thead>
                <tbody>
                @foreach ($history as $h)
                    <tr><td>{{ $date($h->finished_at) }}</td><td class="num">{{ $h->pages_scanned }}</td><td class="num">{{ $h->score ?? '–' }}</td><td class="num">{{ $h->issues_open }}</td><td class="num">{{ $h->issues_new }}</td><td class="num">{{ $h->issues_fixed }}</td></tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </section>

    <section aria-labelledby="checklist">
        <h2 id="checklist">2. {{ tr('WCAG 2.2 A and AA checklist') }}</h2>
        <p>
            {{ tr('{pass} passing, {fail} failing, {na} not applicable, {untested} not tested', ['pass' => $counts['pass'], 'fail' => $counts['fail'], 'na' => $counts['not_applicable'], 'untested' => $counts['not_tested']]) }}.
            {{ tr('A criterion with open automated issues is treated as failing.') }}
        </p>
        <table>
            <thead><tr><th scope="col">{{ tr('Criterion') }}</th><th scope="col">{{ tr('Level') }}</th><th scope="col">{{ tr('Result') }}</th><th scope="col" class="num">{{ tr('Automated issues') }}</th><th scope="col">{{ tr('Notes') }}</th></tr></thead>
            <tbody>
            @foreach ($assessment['criteria'] as $c)
                @php $failing = $c['status'] === 'fail' || ($c['automated_issues'] > 0 && $c['status'] !== 'not_applicable'); @endphp
                <tr>
                    <td><strong>{{ $c['id'] }}</strong> {{ tr($c['title']) }}</td>
                    <td>{{ $c['level'] }}</td>
                    <td style="{{ $failing ? 'color:#b91c1c;font-weight:bold' : '' }}">{{ $failing && $c['status'] !== 'fail' ? tr('Fail (automated)') : $statusWords[$c['status']] }}</td>
                    <td class="num">{{ $c['automated_issues'] ?: '' }}</td>
                    <td class="small">{{ $c['notes'] }}@if ($c['tested_by'])<br><span class="muted">{{ $c['tested_by'] }}, {{ $date($c['tested_at']) }}</span>@endif</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </section>

    <section aria-labelledby="problems" class="page-break">
        <h2 id="problems">3. {{ tr('Open problems found by automated testing') }}</h2>
        @if ($rules->isEmpty())
            <p>{{ tr('No open issues were found by automated testing.') }}</p>
        @else
            <p>{{ tr('Problems are ordered by impact and then by how often they occur. Up to five examples are listed per problem; the full list is in Curbcut.') }}</p>
            @foreach ($rules as $rule)
                <div class="rule">
                    <h3 style="margin-top:0">
                        <span class="badge" style="background: {{ $impactColors[$rule['impact']] ?? '#475569' }}">{{ tr(ucfirst($rule['impact'])) }}</span>
                        {{ $rule['help'] }}
                    </h3>
                    <p class="small muted">
                        {!! e(tr('{elements, plural, one {# element} other {# elements}} on {pages, plural, one {# page} other {# pages}}', ['elements' => $rule['total'], 'pages' => $rule['pages']])) !!}
                        · {{ tr('rule') }} <code>{{ $rule['rule_id'] }}</code>
                        @if ($rule['criteria']) · WCAG {{ implode(', ', $rule['criteria']) }} @else · {{ tr('best practice') }} @endif
                        @if ($rule['help_url']) · <a href="{{ $rule['help_url'] }}">{{ tr('how to fix') }}</a> @endif
                    </p>
                    <table>
                        <thead><tr><th scope="col">{{ tr('Page') }}</th><th scope="col">{{ tr('Element') }}</th><th scope="col">{{ tr('Assignee') }}</th></tr></thead>
                        <tbody>
                        @foreach ($rule['examples'] as $ex)
                            <tr><td class="small">{{ $ex['page'] }}</td><td class="code">{{ $ex['target'] }}</td><td class="small">{{ $ex['assignee'] ?? tr('Unassigned') }}{{ $ex['due_on'] ? ', '.tr('Due {date}', ['date' => $date($ex['due_on'])]) : '' }}</td></tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endforeach
        @endif
    </section>

    <section aria-labelledby="pages">
        <h2 id="pages">4. {{ tr('Pages with the most issues') }}</h2>
        <p>{!! e(tr('{count, plural, one {# page} other {# pages}} known for this site. The {shown} with the most open issues:', ['count' => $page_count, 'shown' => min($page_count, 25)])) !!}</p>
        <table>
            <thead><tr><th scope="col">{{ tr('Page') }}</th><th scope="col" class="num">{{ tr('Critical') }}</th><th scope="col" class="num">{{ tr('Open issues') }}</th></tr></thead>
            <tbody>
            @foreach ($pages as $page)
                <tr><td class="small">{{ $page->title ? $page->title.' — ' : '' }}{{ $page->url }}</td><td class="num">{{ $page->critical }}</td><td class="num">{{ $page->open_issues }}</td></tr>
            @endforeach
            </tbody>
        </table>
    </section>

    <section aria-labelledby="dismissed">
        <h2 id="dismissed">5. {{ tr('Dismissed issues') }}</h2>
        @if ($dismissed->isEmpty())
            <p>{{ tr('No issues have been dismissed.') }}</p>
        @else
            <p>{{ tr('These issues were reviewed and not fixed, with the reason given by the team. They do not count towards the score.') }}</p>
            <table>
                <thead><tr><th scope="col">{{ tr('Problem') }}</th><th scope="col">{{ tr('Reason') }}</th><th scope="col">{{ tr('Justification') }}</th><th scope="col">{{ tr('By') }}</th></tr></thead>
                <tbody>
                @foreach ($dismissed as $issue)
                    <tr>
                        <td>{{ $issue->help }}<br><span class="code">{{ $issue->target }}</span><br><span class="small muted">{{ $issue->page->url }}</span></td>
                        <td>{{ tr(\App\Models\Issue::DISMISS_REASONS[$issue->dismissed_reason] ?? (string) $issue->dismissed_reason) }}</td>
                        <td class="small">{{ $issue->dismissed_note }}</td>
                        <td class="small">{{ $issue->dismisser?->name }}<br>{{ $date($issue->dismissed_at) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </section>

    <section aria-labelledby="trail">
        <h2 id="trail">6. {{ tr('Audit trail') }}</h2>
        @if ($activity->isEmpty())
            <p>{{ tr('No changes have been recorded yet.') }}</p>
        @else
            <p>{{ tr('The most recent {count} recorded changes.', ['count' => $activity->count()]) }}</p>
            <table>
                <thead><tr><th scope="col">{{ tr('Date (UTC)') }}</th><th scope="col">{{ tr('Change') }}</th></tr></thead>
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
