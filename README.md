<div align="center">

# Curbcut

**Find, fix and prove web accessibility. Open source and self-hosted.**

Curbcut scans your websites against WCAG 2.2 AA with [axe-core](https://github.com/dequelabs/axe-core), tracks every issue from the day it appears to the day it is fixed, and keeps that history on your own server.

[![tests](https://github.com/mahamud894/curbcut/actions/workflows/tests.yml/badge.svg)](https://github.com/mahamud894/curbcut/actions/workflows/tests.yml)
[![docker](https://github.com/mahamud894/curbcut/actions/workflows/docker.yml/badge.svg)](https://github.com/mahamud894/curbcut/actions/workflows/docker.yml)
[![License: AGPL v3](https://img.shields.io/badge/License-AGPL_v3-blue.svg)](LICENSE)
![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel&logoColor=white)
![React](https://img.shields.io/badge/React-19-61DAFB?logo=react&logoColor=black)
![WCAG](https://img.shields.io/badge/WCAG-2.2_AA-0e7490)
![Docker](https://img.shields.io/badge/Docker-ready-2496ED?logo=docker&logoColor=white)

<img src="docs/screenshots/overview.png" alt="Curbcut site overview with the accessibility score, open issues by impact, the most common problems, scan history and recent team activity" width="900">

</div>

> **Status:** [v1.0.0](https://github.com/mahamud894/curbcut/releases/tag/v1.0.0) is out. Scanning, issue tracking, the team workflow, the WCAG checklist, accessibility statements, PDF audit reports, multi-client workspaces, the REST API, the GitHub Action and 26 languages work today. See the [roadmap](#roadmap) for what comes next.

## Quick start

```bash
git clone https://github.com/mahamud894/curbcut.git
cd curbcut
docker compose up -d
```

Open <http://localhost:8080>, create an account and add a site. That's it: the web app, scan worker and scheduler are all running. See the [Docker guide](docs/docker.md) for settings, HTTPS and backups, or [install without Docker](docs/installation.md).

## Why Curbcut?

Since **28 June 2025** the [European Accessibility Act](https://ec.europa.eu/social/main.jsp?catId=1202) requires online shops, banks, transport and many other services sold to people in the EU to be accessible. Similar rules exist in the US (ADA), the UK, Canada and elsewhere.

Most teams have the same problem: free checkers test one page at a time, and the platforms that monitor whole sites cost thousands per year. Curbcut is a free, self-hosted alternative that you run on your own infrastructure, so your data never leaves it.

The name comes from the **curb cut**, the small ramp cut into a pavement kerb. It was made for wheelchair users and ended up helping everyone with a pram, a suitcase or a bike. Accessible websites work the same way.

## Features

**Available now**

- **Whole-site scanning.** Finds pages from your sitemap (including sitemap indexes) or by following links, then checks every page in headless Chrome with axe-core against WCAG 2.0, 2.1 and 2.2 A/AA.
- **Change tracking between scans.** Each problem gets a stable fingerprint, so every scan tells you exactly what is **new**, what is **fixed** and what came back.
- **Accessibility score and trend.** A simple, documented 0–100 score per site and its history over time.
- **Issue explorer.** Issues grouped by problem and page, filterable by impact, page, status and text, each with the failing element, the WCAG success criterion and how to fix it.
- **Scheduled scans.** Daily or weekly scans run automatically; manual scans are one click away.
- **Team workflow.** Assign issues one by one, in bulk or for a whole problem at once, set due dates, discuss in comments and get an email when something is assigned to you.
- **Dismiss with a reason.** Mark false positives or accepted risks with a justification. Dismissed issues stay dismissed on later scans and don't count towards the score.
- **Manual WCAG 2.2 checklist.** All 55 level A and AA success criteria, each with pass, fail or not applicable, notes and evidence, the reviewer and date, links to the W3C Understanding docs, and the open automated issues for that criterion.
- **Accessibility statement.** Generated from the checklist and the latest scan, following the structure of the EU model statement: conformance status, known limitations, feedback and contact, enforcement procedure and how the site was assessed. Publish it as a public page that updates itself.
- **PDF audit reports.** One click produces a tagged, accessible PDF with the score, conformance status, checklist results, open problems with fixes, dismissed issues and the audit trail, ready to send to a client or an auditor.
- **Workspaces for teams and agencies.** Group sites into workspaces, one per team or client, with owner, admin and member roles. Switch between them in one click, and see every client's score, critical and overdue issues on one overview. Give a client's developer guest access to a single site.
- **REST API.** Read sites, scans, issues, pages and reports, and start scans from scripts and CI with personal access tokens. See the [API reference](docs/api.md).
- **GitHub Action.** Scan a preview deployment on every pull request and fail the check when it adds new accessibility problems, with a summary in the job. See [GitHub Action](docs/github-action.md).
- **26 languages.** The interface, emails, statements and PDF reports are available in all 24 official EU languages, Russian and Bengali. Each site has its own statement language.
- **Audit trail.** Every assignment, dismissal, comment, checklist result, statement change and report download is recorded with who did it and when.
- **Safe by default.** Private and local network addresses are blocked unless you allow them, to prevent server-side request forgery.
- **Accessible itself.** Curbcut's own screens are checked with axe-core and pass its WCAG 2.2 AA and best-practice rules.

| Issue list with bulk actions | Issue workflow |
| --- | --- |
| <img src="docs/screenshots/issues.png" alt="Issue list grouped by problem, with two issues selected and the bulk assign bar open" width="440"> | <img src="docs/screenshots/issue-workflow.png" alt="A single issue with the failing element, how to fix it, the WCAG criterion, assignee and due date, comments and history" width="440"> |

| All workspaces | PDF audit report |
| --- | --- |
| <img src="docs/screenshots/workspaces.png" alt="The All workspaces overview listing each client workspace with its sites, average and lowest score, open, critical and overdue issues" width="440"> | <img src="docs/screenshots/report.png" alt="The first two pages of a PDF audit report: a cover with the score, conformance status and open issues, then the summary" width="440"> |

| WCAG checklist | Public accessibility statement |
| --- | --- |
| <img src="docs/screenshots/checklist.png" alt="The WCAG 2.2 checklist with progress, filters and pass, fail and not applicable buttons for each success criterion" width="440"> | <img src="docs/screenshots/statement-public.png" alt="The public accessibility statement page listing conformance status, known limitations, contact details and how the site was assessed" width="440"> |

| API tokens for scripts and CI | 26 languages (here in Bengali) |
| --- | --- |
| <img src="docs/screenshots/api-tokens.png" alt="The API tokens settings page with a form to create a token that can start scans, and a list of tokens with their access and when each was last used" width="440"> | <img src="docs/screenshots/languages.png" alt="The site overview in Bengali, with the accessibility score, open issues by impact and the most common problems" width="440"> |

## Real-world example

We pointed Curbcut at the [GOV.UK accessibility tool audit](https://alphagov.github.io/accessibility-tool-audit/), a site the UK Government Digital Service built with 142 deliberate accessibility barriers to test checking tools. One scan crawled **152 pages** and found **125 open issues** (26 critical, 99 serious), led by small touch targets, form fields without labels and low colour contrast.

| Site overview | Issues grouped by problem |
| --- | --- |
| <img src="docs/screenshots/real-world/overview.png" alt="Curbcut overview of the GOV.UK accessibility tool audit site: score 97, 125 open issues of which 26 critical and 99 serious, and the most common problems" width="440"> | <img src="docs/screenshots/real-world/issues.png" alt="The issue list for the same site, grouped into 19 problems such as form elements without labels and image buttons without alternative text" width="440"> |
| **One issue with its fix** | **PDF audit report** |
| <img src="docs/screenshots/real-world/issue-detail.png" alt="Detail of a critical issue: an ol element with the invalid ARIA role breadcrumbs, its HTML, how to fix it and WCAG 4.1.2" width="440"> | <img src="docs/screenshots/real-world/report-cover.png" alt="Cover page of the PDF audit report with score, conformance status, open issues and checklist progress" width="440"> |

Read the full [11-page audit report (PDF)](docs/examples/gov-uk-accessibility-tool-audit-report.pdf). The site was scanned from a local copy of [its source](https://github.com/alphagov/accessibility-tool-audit) (MIT licence), so the address shows 127.0.0.1; the pages are the same as the live site. Most test pages hold one or two problems each, which is why the average score is still high: the issue list, not the score, is what an audit acts on.

## Roadmap

- [x] **Phase 1 — Scan and track:** site management, page discovery, axe-core scanner, new/fixed comparison, score, dashboard, scheduled scans
- [x] **Phase 2 — Compliance workflow:** site members, assign issues with due dates and comments, dismiss with justification, manual WCAG 2.2 checklist, accessibility statement generator with a public page, audit trail
- [x] **Phase 3 — Ready for teams:** PDF audit reports, workspaces with roles for teams and agencies, one-command Docker install, documentation
- [x] **Phase 4 — Developer workflow:** GitHub Action that fails pull requests on new issues, REST API, translations into 26 languages
- [ ] **Next:** optional AI fix suggestions, reviewed translations from native speakers, translated axe-core rule texts

## How it works

```
┌────────────┐   queue job   ┌──────────────┐  JSON lines  ┌───────────────────────────┐
│  Laravel   │ ────────────► │  ScanRunner  │ ◄─────────── │ scanner/scan.mjs (Node)   │
│  web app   │               │  (PHP)       │  per page    │ Playwright + Chrome +     │
└────────────┘               └──────┬───────┘              │ axe-core                  │
      ▲                             │                      └───────────────────────────┘
      │ Inertia + React             ▼
      └──────────────────── sites · pages · scans · issues (database)
```

1. **Discovery.** `PageDiscovery` reads the sitemap, or crawls same-origin links from the start URL, up to the page limit.
2. **Scanning.** Pages are sent in batches to the Node worker, which opens each one in Chrome, injects axe-core and streams the results back.
3. **Recording.** Every failing element becomes an issue with a fingerprint of `site + page + rule + selector`. Issues seen again stay open; issues no longer found on a page that scanned successfully are marked fixed. Pages that fail to load never mark anything as fixed.
4. **Scoring.** Each scanned page starts at 100 and loses 10 points per critical, 5 per serious, 2 per moderate and 1 per minor open issue (minimum 0). The site score is the average across pages.

> Automated tools find only part of the accessibility problems on a site. Curbcut's score shows progress on what machines can detect; it is not a certificate of WCAG conformance. That is what the manual checklist is for.

### Conformance status

The accessibility statement suggests a status from your data:

- **Fully conformant:** every WCAG 2.2 A/AA criterion is reviewed and passes (or is not applicable), and no automated issues are open.
- **Not conformant:** more than half of the applicable criteria are failing.
- **Partially conformant:** everything in between.

A criterion with open automated issues counts as failing even if the checklist says it passes. If an external auditor has assessed the site, you can set the status yourself.

## Documentation

- [Docker](docs/docker.md): install, settings, updates, backups and HTTPS
- [Installing without Docker](docs/installation.md): Nginx, systemd and cron, plus the development setup
- [Configuration](docs/configuration.md): every setting and the `curbcut:user` and `curbcut:scan` commands
- [User guide](docs/user-guide.md): workspaces and roles, scans, issues, the checklist, statements, reports and languages
- [REST API](docs/api.md): tokens, endpoints and examples
- [GitHub Action](docs/github-action.md): fail pull requests that add accessibility problems

### Without Docker, in short

You need PHP 8.2+, Composer, Node.js 20+ and Chrome or Chromium.

```bash
composer install && npm install && npm run build
cp .env.example .env && php artisan key:generate && php artisan migrate

php artisan serve          # web app on http://localhost:8000
php artisan queue:work     # runs the scans
php artisan schedule:work  # queues daily and weekly scans
```

## Development

```bash
./vendor/bin/pest      # PHP tests (scanning, workflow, workspaces, reports, permissions)
npm run lint           # ESLint
npm run format         # Prettier
npx tsc --noEmit       # TypeScript
vendor/bin/pint        # PHP code style
node --test github-action/lib.test.mjs   # GitHub Action
```

The scanner can also be run on its own:

```bash
echo '{"urls":["https://example.com/"]}' | node scanner/scan.mjs
```

**Tech stack:** Laravel 12, Inertia.js, React 19 with TypeScript, Tailwind CSS 4, Playwright, axe-core, Pest, Docker.

## Contributing

Bug reports, ideas and pull requests are welcome. Please open an issue first for larger changes so we can agree on the approach. Run the tests and linters above before sending a pull request.

**Translations** are a great first contribution. Curbcut's 26 languages were drafted with machine help, so native speakers who fix wording in `lang/<code>.json` make a real difference. Adding a language is one JSON file and one line in `config/locales.php`; the tests tell you if anything is missing.

If you rely on assistive technology, your feedback on Curbcut's own interface is especially valuable.

## License

Copyright (C) 2026 Mahamud Hassan.

Curbcut is free software: you can redistribute it and/or modify it under the terms of the [GNU Affero General Public License v3.0](LICENSE). If you run a modified version of Curbcut as a network service, you must make your modified source code available to its users.

Curbcut uses [axe-core](https://github.com/dequelabs/axe-core) by Deque Systems (MPL-2.0) for automated accessibility testing.
