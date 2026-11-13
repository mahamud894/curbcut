#!/usr/bin/env node
/**
 * Renders an HTML document (read from stdin) to an accessible, tagged PDF.
 * Usage: node scanner/pdf.mjs output.pdf < report.html
 */
import { existsSync, mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import process from 'node:process';
import { chromium } from 'playwright-core';

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

async function launch() {
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

const output = process.argv[2];
if (!output) {
    process.stderr.write('Usage: node scanner/pdf.mjs output.pdf < report.html\n');
    process.exit(2);
}

const html = await readStdin();
const browser = await launch();
try {
    const page = await browser.newPage();
    // The report is self-contained; block any network access while rendering.
    await page.route('**/*', (route) => (route.request().url().startsWith('data:') ? route.continue() : route.abort()));
    await page.setContent(html, { waitUntil: 'load' });
    await page.pdf({
        path: output,
        format: 'A4',
        printBackground: true,
        tagged: true,
        outline: true,
        displayHeaderFooter: true,
        headerTemplate: '<span></span>',
        footerTemplate:
            '<div style="width:100%;font-size:8px;color:#555;padding:0 14mm;display:flex;justify-content:space-between;font-family:Arial,sans-serif"><span class="title"></span><span>Page <span class="pageNumber"></span> of <span class="totalPages"></span></span></div>',
        margin: { top: '16mm', bottom: '18mm', left: '14mm', right: '14mm' },
    });
} finally {
    await browser.close();
}
