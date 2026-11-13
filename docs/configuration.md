# Configuration

Curbcut is configured with environment variables: in `.env` for a manual install, or in the `.env` file next to `docker-compose.yml` for Docker. Curbcut-specific options are in `config/curbcut.php`; everything else is standard Laravel.

## Curbcut options

| Variable | Default | Description |
| --- | --- | --- |
| `CURBCUT_REGISTRATION` | `true` | Show the sign-up page. Turn it off once your team has accounts, then add people with `php artisan curbcut:user`. |
| `CURBCUT_ALLOW_PRIVATE_NETWORKS` | `false` | Allow scanning `localhost` and private network addresses (intranets, staging servers, local development). Leave off on a server where people you don't fully trust can add sites. |
| `CURBCUT_CHROME_PATH` | auto-detected | Path to Chrome or Chromium. Curbcut looks in the usual places on Linux, macOS and Windows. The Docker image sets it to `/usr/bin/chromium`. |
| `CURBCUT_NODE_BINARY` | `node` | Node.js binary used for the scanner and PDF reports. |
| `CURBCUT_PAGE_TIMEOUT_MS` | `30000` | How long to wait for one page to load, in milliseconds. |
| `CURBCUT_BATCH_SIZE` | `10` | Pages scanned per browser session. Progress is saved after every page. |
| `CURBCUT_MAX_PAGES_LIMIT` | `500` | Upper limit on pages per scan, whatever a site's own setting says. |
| `CURBCUT_API_RATE_LIMIT` | `120` | REST API requests allowed per token (or IP) per minute. |

## Laravel options you will likely set

| Variable | Description |
| --- | --- |
| `APP_URL` | The address people use to open Curbcut. Used in emails, PDF reports and public statement links. |
| `APP_LOCALE` | Default language when neither the account, a cookie nor the browser picks a supported one. `en` by default. |
| `APP_KEY` | Encryption key. `php artisan key:generate` creates it; the Docker image creates and stores one in `/data/app_key`. |
| `DB_CONNECTION`, `DB_*` | Database. SQLite by default; MySQL, MariaDB and PostgreSQL also work. |
| `QUEUE_CONNECTION` | Queue backend for scans and emails. `database` works out of the box; `redis` is fine for larger installs. |
| `MAIL_MAILER`, `MAIL_*` | Email for assignment notifications. `log` (the default) writes emails to the log instead of sending them. |
| `TRUSTED_PROXIES` | Comma-separated proxy IPs (or `*`) when Curbcut runs behind a reverse proxy, so it knows the original scheme and host. |

## Command-line tools

```bash
php artisan curbcut:user jane@example.com --name="Jane Doe"   # create an account (asks for a password)
php artisan curbcut:scan 3                                    # queue a scan for site 3
php artisan curbcut:schedule-scans                            # queue every daily or weekly scan that is due
```

The scheduler (`php artisan schedule:work`, or a cron entry running `php artisan schedule:run` every minute) calls `curbcut:schedule-scans` once an hour.

## Languages

Curbcut's interface, emails, accessibility statements and PDF reports are available in 26 languages: the 24 official languages of the European Union (Bulgarian, Croatian, Czech, Danish, Dutch, English, Estonian, Finnish, French, German, Greek, Hungarian, Irish, Italian, Latvian, Lithuanian, Maltese, Polish, Portuguese, Romanian, Slovak, Slovenian, Spanish and Swedish), plus Russian and Bengali.

- Everyone picks their own language from the language menu (signed out) or under **Settings → Appearance**. Without a choice, Curbcut follows the browser's language.
- Each site has its own **statement and report language**, so a German client gets a German statement and audit report whatever language your team works in. A report can also be downloaded in another language with `?lang=fr`.
- The problem descriptions and fixes come from axe-core and stay in English.

Translations live in `lang/<code>.json`, with the English text as the key and an [ICU message](https://unicode-org.github.io/icu/userguide/format_parse/messages/) as the value. They were drafted with machine help, so corrections from native speakers are very welcome: edit the file and open a pull request. The test suite checks that every language has every string and keeps every placeholder. The list of languages is in `config/locales.php`.

## Scanner rules

Curbcut runs the axe-core rules tagged `wcag2a`, `wcag2aa`, `wcag21a`, `wcag21aa` and `wcag22aa`, which cover WCAG 2.0, 2.1 and 2.2 at levels A and AA. To change the set (for example to add `best-practice`), edit `wcag_tags` in `config/curbcut.php`.
