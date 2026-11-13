// Helpers for the Curbcut GitHub Action. No dependencies.
import { appendFileSync } from 'node:fs';
import process from 'node:process';

export const IMPACTS = ['critical', 'serious', 'moderate', 'minor'];

export function input(name, fallback = '') {
    const value = process.env[`INPUT_${name.replace(/ /g, '_').toUpperCase()}`];
    return value === undefined || value.trim() === '' ? fallback : value.trim();
}

export function setOutput(name, value) {
    if (process.env.GITHUB_OUTPUT) appendFileSync(process.env.GITHUB_OUTPUT, `${name}=${value}\n`);
}

export function summary(markdown) {
    if (process.env.GITHUB_STEP_SUMMARY) appendFileSync(process.env.GITHUB_STEP_SUMMARY, markdown + '\n');
}

export function escapeData(text) {
    return String(text).replace(/%/g, '%25').replace(/\r/g, '%0D').replace(/\n/g, '%0A');
}

export function escapeProperty(text) {
    return escapeData(text).replace(/:/g, '%3A').replace(/,/g, '%2C');
}

function cell(text) {
    return String(text ?? '').replace(/\|/g, '\\|').replace(/\n/g, ' ');
}

/** True when the new issues include one at the threshold impact or worse. */
export function failsAt(newByImpact, failOn) {
    if (failOn === 'none') return false;
    const levels = IMPACTS.slice(0, IMPACTS.indexOf(failOn) + 1);
    return levels.some((level) => (newByImpact?.[level] ?? 0) > 0);
}

export function renderSummary(check, failOn, failed) {
    const r = check.results ?? { pages: [], issues: [], fixed: [] };
    const fresh = r.issues.filter((i) => i.status === 'new');
    const lines = [];
    lines.push(`## ${failed ? '❌' : '✅'} Curbcut accessibility check`);
    lines.push('');
    lines.push(
        `Checked **${check.pages.scanned}** ${check.pages.scanned === 1 ? 'page' : 'pages'} of ${check.base_url}` +
            (check.pages.failed ? ` (${check.pages.failed} could not be loaded)` : '') +
            `. Fails on: **${failOn}**.`,
    );
    lines.push('');
    lines.push('| New | Already known | Fixed | Critical | Serious | Moderate | Minor |');
    lines.push('| ---: | ---: | ---: | ---: | ---: | ---: | ---: |');
    const n = check.issues.new_by_impact ?? {};
    lines.push(`| ${check.issues.new} | ${check.issues.existing} | ${check.issues.fixed} | ${n.critical ?? 0} | ${n.serious ?? 0} | ${n.moderate ?? 0} | ${n.minor ?? 0} |`);
    lines.push('');
    if (fresh.length) {
        lines.push('### New issues');
        lines.push('');
        lines.push('| Impact | Problem | Page | Element | WCAG |');
        lines.push('| --- | --- | --- | --- | --- |');
        for (const i of fresh.slice(0, 50)) {
            const criteria = (i.wcag ?? []).filter((t) => /^wcag\d{3,}$/.test(t)).map((t) => { const d = t.slice(4); return `${d[0]}.${d[1]}.${d.slice(2)}`; }).join(', ');
            lines.push(`| ${i.impact} | [${cell(i.help)}](${i.help_url ?? '#'}) | \`${cell(i.path)}\` | \`${cell(i.target)}\` | ${criteria} |`);
        }
        if (fresh.length > 50) lines.push(`\n…and ${fresh.length - 50} more.`);
        lines.push('');
    }
    if (r.fixed?.length) {
        lines.push(`<details><summary>${r.fixed.length} known ${r.fixed.length === 1 ? 'issue is' : 'issues are'} fixed on this preview</summary>\n`);
        for (const f of r.fixed.slice(0, 50)) lines.push(`- ${cell(f.help)} on \`${cell(f.path)}\``);
        lines.push('\n</details>\n');
    }
    const failedPages = (r.pages ?? []).filter((p) => p.status !== 'ok');
    if (failedPages.length) {
        lines.push('### Pages that could not be checked');
        for (const p of failedPages) lines.push(`- \`${cell(p.path)}\`: ${cell(p.error)}`);
        lines.push('');
    }
    return lines.join('\n');
}

