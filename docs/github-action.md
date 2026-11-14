# GitHub Action: block pull requests that add accessibility issues

The Curbcut action scans the preview deployment of a pull request, compares it with what Curbcut already knows about the live site, and fails the check when the pull request adds new problems. Existing problems don't block anyone; they are tracked in Curbcut as usual.

## Setup

1. In Curbcut, add the live site and run a first scan. Note its ID: the number in `/sites/<id>`.
2. Under **Settings → API tokens**, create a token with **Allow starting scans and checks**.
3. In your GitHub repository, add the token as a secret named `CURBCUT_TOKEN` (**Settings → Secrets and variables → Actions**).
4. Add a workflow that runs after your preview is deployed.

## Example

```yaml
name: accessibility

on:
  pull_request:

jobs:
  curbcut:
    runs-on: ubuntu-latest
    steps:
      # ... deploy your preview here, or wait for your hosting provider's preview ...

      - uses: mahamud894/curbcut@v1.0.0
        with:
          curbcut-url: https://a11y.example.com
          token: ${{ secrets.CURBCUT_TOKEN }}
          site-id: 1
          preview-url: https://pr-${{ github.event.number }}.preview.example.com
          fail-on: serious
```

Use the latest [release](https://github.com/mahamud894/curbcut/releases) tag, or `@main` to follow development.

With Vercel, Netlify or another host that posts a deployment status, trigger on `deployment_status` instead and use `${{ github.event.deployment_status.target_url }}` as the `preview-url`.

## Inputs

| Input | Default | Description |
| --- | --- | --- |
| `curbcut-url` | required | Address of your Curbcut server. |
| `token` | required | API token with the scan ability. Use a secret. |
| `site-id` | required | The site whose known issues are the baseline. |
| `preview-url` | required | Base address of the deployment to check. |
| `paths` | known pages | Pages to check, one path per line. |
| `max-pages` | `25` | How many known pages to check when `paths` is empty. |
| `fail-on` | `serious` | Fail on new issues of this impact or worse: `critical`, `serious`, `moderate`, `minor`, or `none` to only report. |
| `timeout-minutes` | `20` | How long to wait for the check. |

Checking specific pages:

```yaml
          paths: |
            /
            /pricing
            /checkout
```

## Outputs

| Output | Description |
| --- | --- |
| `check-id` | The check's ID in Curbcut |
| `result` | `passed` or `failed` |
| `new-issues` | Number of new issues |
| `fixed-issues` | Number of known issues that the pull request fixes |

## What you see

- A **job summary** with the counts, a table of every new issue (impact, problem, page, element and WCAG criterion), the issues the pull request fixes and any pages that could not be loaded.
- **Annotations** on the run for each new issue (up to 20), as errors when they reach the `fail-on` level and warnings otherwise.

## Requirements

- Your Curbcut server must be reachable from GitHub's runners, and the preview must be reachable from your Curbcut server.
- A Curbcut queue worker must be running; the check runs on it.
- If the preview is on a private network, set `CURBCUT_ALLOW_PRIVATE_NETWORKS=true` on the Curbcut server.
