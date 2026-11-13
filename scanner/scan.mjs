#!/usr/bin/env node
/**
 * Curbcut scanner worker.
 *
 * Reads a JSON job from stdin:
 *   { "urls": ["https://example.com/"], "timeoutMs": 30000, "tags": ["wcag2a", ...] }
 *
 * Opens every URL in headless Chrome, runs axe-core against it and writes one
 * JSON line per page to stdout, so the Laravel job can store results as they
 * arrive. Diagnostic messages go to stderr.
 */
import process from 'node:process';
import { existsSync, mkdtempSync, readFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { createRequire } from 'node:module';
import { chromium } from 'playwright-core';

const require = createRequire(import.meta.url);
const axeSource = readFileSync(require.resolve('axe-core/axe.min.js'), 'utf8');

const DEFAULT_TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];
const CHROME_CANDIDATES = [
    process.env.CURBCUT_CHROME_PATH,
    '/usr/bin/google-chrome',
    '/usr/bin/google-chrome-stable',
    '/usr/bin/chromium',
    '/usr/bin/chromium-browser',
    '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
    'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
].filter(Boolean);

function readStdin() {
    return new Promise((resolve, reject) => {
        let data = '';
        process.stdin.setEncoding('utf8');
        process.stdin.on('data', (chunk) => (data += chunk));
        process.stdin.on('end', () => resolve(data));
        process.stdin.on('error', reject);
    });
}

async function launchBrowser() {
    // Chrome writes its config and crash reports under $HOME. Give it a private,
    // writable home so it starts whichever user runs it (web server, queue worker, CLI).
    const home = mkdtempSync(join(tmpdir(), 'curbcut-chrome-'));
    process.on('exit', () => rmSync(home, { recursive: true, force: true }));
    const env = { ...process.env, HOME: home, XDG_CONFIG_HOME: join(home, '.config'), XDG_CACHE_HOME: join(home, '.cache') };

    const errors = [];
    for (const executablePath of CHROME_CANDIDATES) {
        if (!existsSync(executablePath)) continue;
        try {
            return await chromium.launch({ executablePath, env, headless: true, args: ['--no-sandbox', '--disable-dev-shm-usage'] });
        } catch (error) {
            errors.push(`${executablePath}: ${error.message.split('\n').slice(0, 6).join(' ')}`);
        }
    }
    throw new Error(
        errors.length
            ? `Could not start Chrome. ${errors.join(' | ')}`
            : `Chrome or Chromium was not found. Install it or set CURBCUT_CHROME_PATH.`,
    );
}

function emit(result) {
    process.stdout.write(JSON.stringify(result) + '\n');
}

async function scanUrl(context, url, timeoutMs, tags) {
    const page = await context.newPage();
    const started = Date.now();
    try {
        let response;
        try {
            response = await page.goto(url, { waitUntil: 'networkidle', timeout: timeoutMs });
        } catch {
            // Pages with long-polling or analytics never go idle; fall back to the load event.
            response = await page.goto(url, { waitUntil: 'load', timeout: timeoutMs });
        }

        const httpStatus = response?.status() ?? null;
        await page.addScriptTag({ content: axeSource });
        const results = await page.evaluate(
            async (runTags) =>
                // eslint-disable-next-line no-undef
                await axe.run(document, { runOnly: { type: 'tag', values: runTags }, resultTypes: ['violations'] }),
            tags,
        );

        emit({
            url,
            status: 'ok',
            httpStatus,
            title: await page.title(),
            durationMs: Date.now() - started,
            passes: results.passes.length,
            incomplete: results.incomplete.length,
            violations: results.violations.map((violation) => ({
                id: violation.id,
                impact: violation.impact,
                tags: violation.tags,
                help: violation.help,
                description: violation.description,
                helpUrl: violation.helpUrl,
                nodes: violation.nodes.map((node) => ({
                    target: node.target.map((part) => (Array.isArray(part) ? part.join(' ') : part)).join(' '),
                    html: node.html.slice(0, 2000),
                    failureSummary: node.failureSummary ?? '',
                })),
            })),
        });
    } catch (error) {
        emit({ url, status: 'failed', error: String(error?.message ?? error).slice(0, 1000), durationMs: Date.now() - started });
    } finally {
        await page.close().catch(() => {});
    }
}

async function main() {
    const job = JSON.parse((await readStdin()) || '{}');
    const urls = Array.isArray(job.urls) ? job.urls : [];
    const timeoutMs = Number(job.timeoutMs) || 30000;
    const tags = Array.isArray(job.tags) && job.tags.length ? job.tags : DEFAULT_TAGS;

    const browser = await launchBrowser();
    const context = await browser.newContext({ userAgent: 'CurbcutAccessibilityScanner/1.0 (+https://github.com/mahamud894/curbcut)' });
    try {
        for (const url of urls) {
            await scanUrl(context, url, timeoutMs, tags);
        }
    } finally {
        await browser.close();
    }
}

main().catch((error) => {
    process.stderr.write(`${error?.stack ?? error}\n`);
    process.exit(1);
});
