<?php

return [
    // Node.js binary used to run the scanner worker (scanner/scan.mjs).
    'node_binary' => env('CURBCUT_NODE_BINARY', 'node'),

    'scanner_script' => env('CURBCUT_SCANNER_SCRIPT', base_path('scanner/scan.mjs')),

    // Optional explicit Chrome/Chromium path for the scanner worker.
    'chrome_path' => env('CURBCUT_CHROME_PATH'),

    // Per-page navigation timeout in milliseconds.
    'page_timeout_ms' => (int) env('CURBCUT_PAGE_TIMEOUT_MS', 30000),

    // Pages handed to one scanner process; progress is saved after each page.
    'batch_size' => (int) env('CURBCUT_BATCH_SIZE', 10),

    // Hard upper bound on pages per site, whatever the site setting says.
    'max_pages_limit' => (int) env('CURBCUT_MAX_PAGES_LIMIT', 500),

    // Block scanning of localhost / private network addresses (SSRF protection).
    // Turn on for intranet use or local development.
    'allow_private_networks' => (bool) env('CURBCUT_ALLOW_PRIVATE_NETWORKS', false),

    // Allow anyone to create an account from the sign-up page. Turn off once your
    // team has signed up; add people later with `php artisan curbcut:user`.
    'registration' => (bool) env('CURBCUT_REGISTRATION', true),

    // WCAG tags passed to axe-core.
    'wcag_tags' => ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'],
];
