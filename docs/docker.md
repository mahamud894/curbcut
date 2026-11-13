# Running Curbcut with Docker

The Docker setup is the quickest way to run Curbcut on a server. One image contains everything: PHP and Apache for the web app, Node.js and Chromium for scanning and PDF reports, and SQLite for the data.

## Quick start

You need Docker with the Compose plugin (Docker Desktop, or Docker Engine 24 or newer).

```bash
git clone https://github.com/mahamud894/curbcut.git
cd curbcut
docker compose up -d
```

Open <http://localhost:8080>, create your account and add your first site.

The first `docker compose up` builds the image, which takes a few minutes. After that, starting and stopping is instant.

## What runs

`docker-compose.yml` starts three containers from the same image:

| Service     | Command     | What it does                                                         |
| ----------- | ----------- | -------------------------------------------------------------------- |
| `app`       | `web`       | Runs database migrations, then serves the web app on port 80.        |
| `worker`    | `worker`    | Runs scans and sends emails from the queue.                          |
| `scheduler` | `scheduler` | Queues daily and weekly scans when they are due (checked every hour). |

The worker and scheduler wait until the `app` container is healthy, so the database is always migrated first.

All data lives in the `curbcut-data` volume, mounted at `/data`:

- `/data/database.sqlite`: the database
- `/data/app_key`: the encryption key, generated on first start

Back up this volume and you have backed up Curbcut.

## Settings

Put settings in a `.env` file next to `docker-compose.yml`. Compose reads it automatically. For example:

```dotenv
APP_URL=https://a11y.example.com
CURBCUT_PORT=8080

# Close sign-up once your team has accounts.
CURBCUT_REGISTRATION=false

# Send assignment emails.
MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=curbcut@example.com
MAIL_PASSWORD=secret
MAIL_FROM_ADDRESS=curbcut@example.com
```

Then apply them with `docker compose up -d`. See [configuration.md](configuration.md) for every option.

## Common tasks

```bash
# Create an account (useful when sign-up is closed)
docker compose exec app php artisan curbcut:user jane@example.com --name="Jane Doe"

# Start a scan of site 3 from the command line
docker compose exec app php artisan curbcut:scan 3

# Follow the logs
docker compose logs -f worker

# Stop everything (data is kept)
docker compose down
```

## Updating

```bash
git pull
docker compose up -d --build
```

Migrations run automatically when the `app` container starts.

## Backups

The simplest backup is a copy of the SQLite file while the containers are running:

```bash
docker compose exec app sqlite3 /data/database.sqlite ".backup '/data/backup.sqlite'"
docker compose cp app:/data/backup.sqlite ./curbcut-backup-$(date +%F).sqlite
```

Also keep `/data/app_key` (or set `APP_KEY` yourself). Without it, existing sessions and encrypted values cannot be read.

## HTTPS and reverse proxies

The container speaks plain HTTP. Put it behind the reverse proxy you already use (Caddy, Nginx, Traefik, a cloud load balancer) and let that handle certificates. Set:

```dotenv
APP_URL=https://a11y.example.com
TRUSTED_PROXIES=*
```

`TRUSTED_PROXIES` makes Curbcut trust the `X-Forwarded-*` headers, so links and redirects use `https`. Use the proxy's IP address instead of `*` if the container port is reachable from anywhere other than the proxy.

A minimal Caddy example:

```caddy
a11y.example.com {
    reverse_proxy localhost:8080
}
```

## Scanning sites on your own network

By default Curbcut refuses to scan `localhost` and private network addresses (10.x, 172.16–31.x, 192.168.x and so on). This protects your server from server-side request forgery when other people can add sites.

To scan an intranet or a staging server on your network, set:

```dotenv
CURBCUT_ALLOW_PRIVATE_NETWORKS=true
```

Only do this if you trust everyone who can sign in.

## Using a prebuilt image

Each push to `main` publishes an image to the GitHub Container Registry. To use it instead of building locally:

```bash
docker pull ghcr.io/mahamud894/curbcut:latest
docker compose up -d --no-build
```

## Using MySQL or PostgreSQL

SQLite handles a team with hundreds of sites comfortably. If you prefer a database server, set the usual Laravel variables on all three services:

```yaml
environment:
  DB_CONNECTION: pgsql
  DB_HOST: postgres
  DB_PORT: 5432
  DB_DATABASE: curbcut
  DB_USERNAME: curbcut
  DB_PASSWORD: secret
```

The image includes the MySQL and PostgreSQL drivers, so no rebuild is needed. Add your database container to the compose file, or point `DB_HOST` at an existing server.

## Troubleshooting

**Scans stay "Queued".** The worker isn't running. Check `docker compose ps` and `docker compose logs worker`.

**Scans fail with "Could not start Chrome".** The worker needs enough shared memory for Chromium. The compose file sets `shm_size: 1gb`; keep it if you write your own compose file.

**"This address is not allowed".** The site resolves to a private network address. See [Scanning sites on your own network](#scanning-sites-on-your-own-network).

**Links point to `http://localhost`.** Set `APP_URL` to the address people use.
