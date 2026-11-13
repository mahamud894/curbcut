# REST API

Curbcut has a JSON API for scripts, CI pipelines and dashboards. The [GitHub Action](github-action.md) is built on it.

## Authentication

Create a token under **Settings → API tokens**. Choose:

- **Read only**: read sites, scans, issues, pages and reports.
- **Read and scan** ("Allow starting scans and checks"): also start scans and checks.

A token acts as you: it sees exactly the sites you can see in Curbcut. Send it as a bearer token:

```bash
curl -H "Authorization: Bearer $CURBCUT_TOKEN" -H "Accept: application/json" \
     https://a11y.example.com/api/v1/sites
```

Revoke a token at any time on the same page. Requests are limited to 120 per minute per user (`CURBCUT_API_RATE_LIMIT`).

## Conventions

- Base URL: `https://<your-curbcut>/api/v1`
- Responses wrap results in `data`. Lists are paginated, with `links` and `meta` (`?page=2`).
- Dates are ISO 8601 in UTC.
- Errors return JSON with a `message`, and `errors` for validation problems. Status codes: `401` no or bad token, `403` no access or missing ability, `404` not found, `409` a scan is already running, `422` invalid input, `429` too many requests.

## Endpoints

| Method | Path | Ability | Description |
| --- | --- | --- | --- |
| GET | `/me` | any | The token's user, abilities and workspaces |
| GET | `/sites` | read | Sites you can see. Filter with `?workspace=<id>` |
| GET | `/sites/{site}` | read | One site with its score and open issues by impact |
| GET | `/sites/{site}/scans` | read | Scans, newest first |
| POST | `/sites/{site}/scans` | scan | Start a scan. Returns `202`, or `409` if one is already queued or running |
| GET | `/sites/{site}/scans/{scan}` | read | One scan: status, pages, score, new, fixed and open issues |
| GET | `/sites/{site}/issues` | read | Issues. Filters: `status` (open, fixed, dismissed; default open), `impact`, `rule`, `page`, `per_page` (max 200) |
| GET | `/sites/{site}/issues/{issue}` | read | One issue with the failing element, HTML, WCAG criteria and how to fix it |
| GET | `/sites/{site}/pages` | read | Known pages with their open issue counts |
| GET | `/sites/{site}/report` | read | The PDF audit report |
| POST | `/sites/{site}/checks` | scan | Check a preview deployment (see below) |
| GET | `/sites/{site}/checks/{check}` | read | A check's status and, when finished, its results |

### Example: a site

```json
{
  "data": {
    "id": 1,
    "name": "Demo shop",
    "url": "https://shop.example.com/",
    "workspace": { "id": 3, "name": "Client: Riverside Bakery" },
    "score": 88,
    "open_issues": 4,
    "open_by_impact": { "critical": 3, "serious": 1, "moderate": 0, "minor": 0 },
    "last_scanned_at": "2026-11-13T18:07:39+00:00",
    "scanning": false,
    "web_url": "https://a11y.example.com/sites/1"
  }
}
```

### Example: start a scan and wait for it

```bash
API=https://a11y.example.com/api/v1
AUTH="Authorization: Bearer $CURBCUT_TOKEN"

scan=$(curl -fsS -X POST -H "$AUTH" -H "Accept: application/json" $API/sites/1/scans | jq -r .data.id)
until [ "$(curl -fsS -H "$AUTH" $API/sites/1/scans/$scan | jq -r .data.status)" = completed ]; do sleep 5; done
curl -fsS -H "$AUTH" $API/sites/1/scans/$scan | jq .data.score
```

## Checks: testing a preview before it goes live

A **check** scans another copy of a site, such as a pull request preview or a staging server, and compares what it finds with the issues Curbcut already knows for that site. The site's own issues, score and history are not changed.

```bash
curl -fsS -X POST -H "$AUTH" -H "Content-Type: application/json" -H "Accept: application/json" \
     -d '{"base_url": "https://pr-42.preview.example.com", "ref": "pr-42"}' \
     $API/sites/1/checks
```

Body fields:

| Field | Required | Description |
| --- | --- | --- |
| `base_url` | yes | Address of the copy to check. Each path is appended to it. |
| `paths` | no | List of paths such as `["/", "/checkout"]`. By default Curbcut uses the site's known pages, those with the most open issues first. |
| `max_pages` | no | When `paths` is not given, how many known pages to check (default: the site's page limit, at most 25). |
| `ref` | no | A label, such as a branch or pull request, stored with the check. |

The check runs on the queue. Poll `GET /sites/{site}/checks/{check}` until `status` is `completed` or `failed`. A finished check contains:

- `issues.new`, `issues.existing`, `issues.fixed` and `issues.new_by_impact`
- `results.issues`: every problem found, each with `status` `new`, `existing` or `dismissed`, the path, rule, impact, element, HTML, WCAG tags and how to fix it
- `results.fixed`: known open issues that are gone on the preview
- `results.pages`: each page and whether it loaded

Problems are matched by page path, rule and element. When markup changes move an element (its selector changes from `button:nth-child(3)` to `button:nth-child(4)`), Curbcut pairs it with the known issue of the same rule on the same page, so it is not reported as both new and fixed.

The preview must be reachable from the Curbcut server. Private network addresses are refused unless `CURBCUT_ALLOW_PRIVATE_NETWORKS=true`.
