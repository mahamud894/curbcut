// Curbcut GitHub Action: no dependencies, runs on the runner's own Node.js.
import process from 'node:process';
import { escapeData, escapeProperty, failsAt, IMPACTS, input, renderSummary, setOutput, summary } from './lib.mjs';

async function api(base, token, path, options = {}) {
    const response = await fetch(`${base}/api/v1${path}`, {
        ...options,
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', Authorization: `Bearer ${token}`, ...(options.headers ?? {}) },
    });
    const text = await response.text();
    let body;
    try {
        body = JSON.parse(text);
    } catch {
        body = { message: text.slice(0, 300) };
    }
    if (!response.ok) {
        const details = body.errors ? ' ' + Object.values(body.errors).flat().join(' ') : '';
        throw new Error(`Curbcut answered ${response.status} for ${path}: ${body.message ?? 'error'}${details}`);
    }
    return body;
}

async function main() {
    const base = input('curbcut-url').replace(/\/+$/, '');
    const token = input('token');
    const siteId = input('site-id');
    const previewUrl = input('preview-url');
    const failOn = input('fail-on', 'serious').toLowerCase();
    const timeoutMs = Number(input('timeout-minutes', '20')) * 60_000;
    const paths = input('paths')
        .split(/\r?\n/)
        .map((p) => p.trim())
        .filter(Boolean);

    if (!base || !token || !siteId || !previewUrl) throw new Error('curbcut-url, token, site-id and preview-url are required.');
    if (![...IMPACTS, 'none'].includes(failOn)) throw new Error(`fail-on must be one of ${[...IMPACTS, 'none'].join(', ')}.`);
    if (process.env.GITHUB_ACTIONS) console.log(`::add-mask::${token}`);

    const ref = process.env.GITHUB_HEAD_REF || process.env.GITHUB_REF_NAME || process.env.GITHUB_SHA || null;
    const body = { base_url: previewUrl, ref, ...(paths.length ? { paths } : { max_pages: Number(input('max-pages', '25')) }) };
    const started = await api(base, token, `/sites/${siteId}/checks`, { method: 'POST', body: JSON.stringify(body) });
    const id = started.data.id;
    setOutput('check-id', id);
    console.log(`Curbcut check #${id} started for ${previewUrl} (${started.data.pages.requested} pages).`);

    const deadline = Date.now() + timeoutMs;
    let check = started.data;
    while (!['completed', 'failed'].includes(check.status)) {
        if (Date.now() > deadline) throw new Error(`The check did not finish within ${timeoutMs / 60_000} minutes. Is a Curbcut queue worker running?`);
        await new Promise((resolve) => setTimeout(resolve, 3000));
        check = (await api(base, token, `/sites/${siteId}/checks/${id}`)).data;
        process.stdout.write(`  ${check.status}: ${check.pages.scanned + check.pages.failed}/${check.pages.requested} pages\n`);
    }

    if (check.status === 'failed') {
        setOutput('result', 'failed');
        throw new Error(`The check failed: ${check.error ?? 'unknown error'}`);
    }

    const failed = failsAt(check.issues.new_by_impact, failOn);
    setOutput('result', failed ? 'failed' : 'passed');
    setOutput('new-issues', check.issues.new);
    setOutput('fixed-issues', check.issues.fixed);
    summary(renderSummary(check, failOn, failed));

    const fresh = (check.results?.issues ?? []).filter((i) => i.status === 'new');
    for (const i of fresh.slice(0, 20)) {
        const level = failsAt({ [i.impact]: 1 }, failOn) ? 'error' : 'warning';
        console.log(`::${level} title=${escapeProperty(`${i.impact}: ${i.help}`)}::${escapeData(`${i.path} ${i.target}\n${i.failure_summary ?? ''}\n${i.help_url ?? ''}`)}`);
    }

    console.log(`New: ${check.issues.new}, already known: ${check.issues.existing}, fixed: ${check.issues.fixed}.`);
    if (failed) {
        process.exitCode = 1;
        console.log(`::error::Found new accessibility issues at "${failOn}" impact or worse.`);
    }
}

main().catch((error) => {
    console.log(`::error::${escapeData(error.message)}`);
    process.exitCode = 1;
});
