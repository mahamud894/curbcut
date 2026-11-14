# Changelog

All notable changes to Curbcut are listed here. Versions follow [Semantic Versioning](https://semver.org/).

## [1.0.0] - 2026-11-14

The first stable release.

### Scan and track

- Whole-site scanning: pages from the sitemap (including sitemap indexes) or by following links, checked in headless Chrome with axe-core against WCAG 2.0, 2.1 and 2.2 A and AA.
- New and fixed issues between scans, based on a stable fingerprint for each problem.
- A documented 0–100 accessibility score per site and its history.
- Issue explorer grouped by problem and page, with filters, the failing element, the WCAG criterion and how to fix it.
- Daily or weekly scheduled scans.

### Compliance workflow

- Assign issues one by one, in bulk or by problem, with due dates, comments and email notifications.
- Dismiss issues as false positives or accepted risks with a reason; they stay dismissed and leave the score.
- Manual checklist for all 55 WCAG 2.2 A and AA success criteria with notes, evidence and reviewer.
- Accessibility statement following the EU model statement, published as a public page that updates itself.
- Audit trail of every assignment, dismissal, comment, checklist result, statement change and report download.

### Teams

- Tagged, accessible PDF audit reports.
- Workspaces with owner, admin and member roles, an overview of every client, and guest access to a single site.
- One-command Docker install (web app, worker and scheduler), plus a manual install guide.
- `curbcut:user` and `curbcut:scan` commands, and a switch to turn off sign-up.

### Developer workflow

- REST API (`/api/v1`) with personal access tokens and read or read-and-scan access.
- GitHub Action that scans a pull request's preview deployment and fails the check when it adds new problems.
- The interface, emails, statements and PDF reports in 26 languages: the 24 official EU languages, Russian and Bengali. Each site has its own statement and report language.

### Known limitations

- Problem descriptions and fixes come from axe-core and are shown in English.
- Translations were drafted with machine help and still need review by native speakers.

[1.0.0]: https://github.com/mahamud894/curbcut/releases/tag/v1.0.0
