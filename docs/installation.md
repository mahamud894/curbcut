# Installing Curbcut without Docker

Use this guide to run Curbcut directly on a Linux server, or on your own computer for development. If you can use Docker, [docker.md](docker.md) is quicker.

## Requirements

- PHP 8.2 or newer with the `pdo_sqlite` (or `pdo_mysql` / `pdo_pgsql`), `mbstring`, `xml`, `curl` and `zip` extensions
- Composer 2
- Node.js 20 or newer
- Google Chrome or Chromium on the machine that runs the queue worker
- A web server (Nginx or Apache) for production

On Ubuntu 24.04, for example:

```bash
sudo apt install php8.3-cli php8.3-fpm php8.3-sqlite3 php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip \
    composer chromium-browser nginx
# Node.js 22 from https://nodejs.org or your preferred package source
```

## Install

```bash
git clone https://github.com/mahamud894/curbcut.git
cd curbcut

composer install --no-dev --optimize-autoloader
npm ci
npm run build

cp .env.example .env
php artisan key:generate
php artisan migrate --force
```

Then edit `.env`. At minimum set `APP_URL`, `APP_ENV=production` and `APP_DEBUG=false`. See [configuration.md](configuration.md) for the rest.

Finally, cache the configuration:

```bash
php artisan optimize
```

Run `php artisan optimize` again whenever you change `.env`.

## The three processes

Curbcut needs:

1. **The web app**, served by PHP-FPM behind Nginx or Apache.
2. **A queue worker** (`php artisan queue:work`), which runs scans and sends emails.
3. **The scheduler** (`php artisan schedule:run` every minute), which starts daily and weekly scans.

### Web server (Nginx)

```nginx
server {
    listen 80;
    server_name a11y.example.com;
    root /var/www/curbcut/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    }
}
```

Make `storage/`, `bootstrap/cache/` and the SQLite file (`database/database.sqlite`) writable by the web server user, usually `www-data`.

### Queue worker (systemd)

`/etc/systemd/system/curbcut-worker.service`:

```ini
[Unit]
Description=Curbcut queue worker
After=network.target

[Service]
User=www-data
WorkingDirectory=/var/www/curbcut
ExecStart=/usr/bin/php artisan queue:work --sleep=3 --tries=1 --timeout=3600
Restart=always
Environment=HOME=/tmp

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl enable --now curbcut-worker
```

A large site can take a while to scan, which is why the timeout is an hour. After deploying new code, run `php artisan queue:restart` so the worker picks it up.

### Scheduler (cron)

```cron
* * * * * www-data cd /var/www/curbcut && php artisan schedule:run >> /dev/null 2>&1
```

## Your first account

Open Curbcut in a browser and sign up. Each account gets its own workspace.

On a public server, close sign-up afterwards with `CURBCUT_REGISTRATION=false` and add colleagues from the command line:

```bash
php artisan curbcut:user jane@example.com --name="Jane Doe"
```

Then invite them to your workspace from **Workspace settings**.

## Development setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
composer dev
```

`composer dev` starts the web server, queue worker, log viewer and Vite together. Set `CURBCUT_ALLOW_PRIVATE_NETWORKS=true` in `.env` to scan sites on `localhost`.

Tests and code style:

```bash
./vendor/bin/pest
vendor/bin/pint
npm run lint
npm run format
npx tsc --noEmit
```

## Updating

```bash
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan optimize
php artisan queue:restart
```
