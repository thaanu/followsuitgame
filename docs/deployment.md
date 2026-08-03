# Follow Suit — production deployment

Follow Suit is not a plain request/response Laravel app. Beyond PHP-FPM and MySQL it
needs three long-lived processes, and skipping any of them degrades or breaks the game:

| Process | What breaks without it |
| --- | --- |
| **Reverb** (WebSocket server) | No live updates. The browser falls back to a ~20s heartbeat poll, so the game is playable but feels dead. |
| **Queue worker** | Broadcasts never leave the app, **and the turn timer and disconnection policy never fire**. If a host enables the turn timer, turns hang forever. |
| **Scheduler cron** | Abandoned games are never cancelled, and disconnected players are never detected. `game_cards` grows without bound. |

Read that table twice. The queue worker in particular is not optional — it is part of
the game engine, not just an optimisation.

---

## 1. Pre-flight checklist

- [ ] `APP_URL` matches the public HTTPS URL exactly
- [ ] `APP_DEBUG=false` and `APP_ENV=production`
- [ ] `APP_KEY` generated (and backed up — losing it invalidates every session)
- [ ] MySQL 8+ reachable, database created with `utf8mb4`
- [ ] `SESSION_DRIVER` on a shared store if you run more than one app node
- [ ] `BROADCAST_CONNECTION=reverb`, `QUEUE_CONNECTION=database` (or `redis`)
- [ ] Reverb credentials set, and `REVERB_SCHEME=https` / `REVERB_PORT=443` behind TLS
- [ ] `TrustProxies` configured if you sit behind a load balancer (see §7)

### Why `APP_URL` matters more than usual

Invitation links and QR codes are generated server-side from the named route:

```php
// app/Models/Game.php
public function invitationUrl(): string
{
    return route('games.join.show', ['code' => ..., 'token' => ...]);
}
```

If `APP_URL` is wrong, every host will share a link their friends cannot open. This is
the single most common way to ship this app broken.

---

## 2. Option A — Laravel Cloud (recommended)

Laravel Cloud runs the Reverb, queue and scheduler pieces for you, which removes most
of this document.

1. Connect the repository and pick a **MySQL** database.
2. Enable **Reverb** on the environment. Cloud provisions the credentials and TLS
   endpoint and injects `REVERB_*` for you — do not set them by hand.
3. Add a **queue worker** process running `php artisan queue:work --tries=3`.
4. Enable the **scheduler**.
5. Set the deploy command:

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan db:seed --class=RulePresetSeeder --force
php artisan optimize
```

6. Set `APP_URL` to the environment's public URL.

That's it. Skip to §7 for the load-balancer notes and §9 for the security checklist.

---

## 3. Option B — VPS with nginx, PHP-FPM and Supervisor

A single-server setup that comfortably handles a few hundred concurrent players.

### 3.1 Server packages

```bash
sudo apt update
sudo apt install -y nginx mysql-server supervisor redis-server \
  php8.3-fpm php8.3-mysql php8.3-mbstring php8.3-xml php8.3-curl \
  php8.3-zip php8.3-bcmath php8.3-redis unzip git
```

Node is only needed to build assets. Build on CI if you'd rather keep it off the box.

### 3.2 Application

```bash
sudo mkdir -p /var/www/followsuit
sudo chown -R $USER:www-data /var/www/followsuit
cd /var/www/followsuit

git clone <repository> .
composer install --no-dev --optimize-autoloader
npm ci && npm run build

cp .env.example .env
php artisan key:generate
```

Set permissions so PHP-FPM can write logs and caches:

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

### 3.3 Database

```bash
sudo mysql -e "CREATE DATABASE followsuitdb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -e "CREATE USER 'followsuit'@'localhost' IDENTIFIED BY 'a-strong-password';"
sudo mysql -e "GRANT ALL PRIVILEGES ON followsuitdb.* TO 'followsuit'@'localhost';"
sudo mysql -e "FLUSH PRIVILEGES;"

php artisan migrate --force
php artisan db:seed --class=RulePresetSeeder --force
```

> Never run `migrate:fresh` on production — it drops every table. The app guards
> against this via `DB::prohibitDestructiveCommands(app()->isProduction())` in
> `AppServiceProvider`, but do not rely on that alone.

### 3.4 nginx

Two blocks matter: the normal Laravel site, and a WebSocket proxy for Reverb. Putting
Reverb behind the same hostname on `/app` avoids a second certificate and dodges most
corporate firewalls, since it looks like ordinary HTTPS traffic on 443.

```nginx
server {
    listen 80;
    server_name followsuit.example.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name followsuit.example.com;

    root /var/www/followsuit/public;
    index index.php;

    ssl_certificate     /etc/letsencrypt/live/followsuit.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/followsuit.example.com/privkey.pem;

    charset utf-8;
    client_max_body_size 8m;

    # --- Reverb WebSocket endpoint -------------------------------------------
    # Reverb serves its Pusher-protocol endpoints under /app and /apps.
    location ~ ^/(app|apps) {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Host              $host;
        proxy_set_header X-Real-IP         $remote_addr;
        proxy_set_header X-Forwarded-For   $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header Upgrade           $http_upgrade;
        proxy_set_header Connection        "Upgrade";

        # WebSockets are long-lived; do not let nginx time them out.
        proxy_read_timeout  3600s;
        proxy_send_timeout  3600s;
    }

    # --- Laravel --------------------------------------------------------------
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* { deny all; }

    # Built assets are content-hashed, so they can be cached hard.
    location /build/ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    access_log /var/log/nginx/followsuit-access.log;
    error_log  /var/log/nginx/followsuit-error.log;
}
```

With that proxy in place the browser connects to `wss://followsuit.example.com/app/...`
on port 443, so the client env is:

```ini
REVERB_HOST="followsuit.example.com"
REVERB_PORT=443
REVERB_SCHEME=https

REVERB_SERVER_HOST=127.0.0.1     # Reverb itself only listens locally
REVERB_SERVER_PORT=8080
```

`REVERB_HOST`/`REVERB_PORT`/`REVERB_SCHEME` describe what the **browser** connects to.
`REVERB_SERVER_HOST`/`REVERB_SERVER_PORT` describe where the **process** binds. Mixing
these two up is the second most common way to ship this app broken.

### 3.5 Supervisor — the three processes

```ini
; /etc/supervisor/conf.d/followsuit.conf

[program:followsuit-reverb]
process_name=%(program_name)s
command=php /var/www/followsuit/artisan reverb:start
directory=/var/www/followsuit
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/followsuit/reverb.log
stopwaitsecs=10

[program:followsuit-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/followsuit/artisan queue:work --tries=3 --max-time=3600 --sleep=1
directory=/var/www/followsuit
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/followsuit/queue.log
stopwaitsecs=3600

[program:followsuit-scheduler]
process_name=%(program_name)s
command=php /var/www/followsuit/artisan schedule:work
directory=/var/www/followsuit
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/followsuit/scheduler.log
```

```bash
sudo mkdir -p /var/log/followsuit && sudo chown www-data:www-data /var/log/followsuit
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status
```

Notes specific to this app:

- **`--sleep=1`** on the queue worker. The default is 3 seconds, which adds up to 3s of
  lag to every card played. One second keeps the table feeling live.
- **`numprocs=2`** is a reasonable start. Broadcast jobs are tiny; scale on queue depth,
  not on player count.
- **`schedule:work`** under Supervisor is an alternative to a cron entry. If you prefer
  cron, use this instead and drop the scheduler program:

  ```
  * * * * * cd /var/www/followsuit && php artisan schedule:run >> /dev/null 2>&1
  ```

The scheduler runs `games:cleanup` every minute and `games:cleanup --purge` nightly at
03:15 (see `routes/console.php`).

---

## 4. Option C — Pusher instead of Reverb

If you don't want to run a WebSocket process at all, the app speaks the Pusher protocol
already. Swap the driver:

```ini
BROADCAST_CONNECTION=pusher
PUSHER_APP_ID=...
PUSHER_APP_KEY=...
PUSHER_APP_SECRET=...
PUSHER_APP_CLUSTER=eu
```

Then change the broadcaster in `resources/js/composables/useGameChannel.ts` from
`'reverb'` to `'pusher'` and pass `cluster` instead of `wsHost`/`wsPort`. The queue
worker and scheduler are still required.

This trades a monthly bill for one less process to babysit. For a small game night app
the free tier is usually enough; a busy game emits roughly 4–6 messages per card played.

---

## 5. Environment reference

```ini
APP_NAME="Follow Suit"
APP_ENV=production
APP_KEY=base64:...
APP_DEBUG=false
APP_URL=https://followsuit.example.com

LOG_CHANNEL=stack
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=followsuitdb
DB_USERNAME=followsuit
DB_PASSWORD=a-strong-password

SESSION_DRIVER=database          # or redis behind a load balancer
SESSION_LIFETIME=1440            # 24h, so a player's seat outlives a long game
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax

QUEUE_CONNECTION=database        # or redis
CACHE_STORE=database             # or redis

BROADCAST_CONNECTION=reverb
REVERB_APP_ID=...
REVERB_APP_KEY=...
REVERB_APP_SECRET=...
REVERB_HOST="followsuit.example.com"
REVERB_PORT=443
REVERB_SCHEME=https
REVERB_SERVER_HOST=127.0.0.1
REVERB_SERVER_PORT=8080

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

### Game tuning

All optional — the defaults in `config/followsuit.php` are sensible.

```ini
FOLLOW_SUIT_GAME_LIFETIME_HOURS=24        # before an unfinished game is auto-cancelled
FOLLOW_SUIT_PURGE_AFTER_DAYS=7            # how long finished games are kept
FOLLOW_SUIT_HEARTBEAT_SECONDS=20          # browser presence ping
FOLLOW_SUIT_PRESENCE_TIMEOUT_SECONDS=60   # silence before "disconnected"
FOLLOW_SUIT_RATE_CREATE=10                # game creations/min per IP
FOLLOW_SUIT_RATE_JOIN=20                  # join attempts/min per IP
FOLLOW_SUIT_RATE_MOVES=120                # moves/min per player
```

> `VITE_*` variables are **baked into the JavaScript bundle at build time**. Changing
> them requires `npm run build` again — a `config:clear` will not help.

---

## 6. Deploy script

```bash
#!/usr/bin/env bash
set -euo pipefail

cd /var/www/followsuit

php artisan down --render="errors::503" --retry=15

git pull origin main
composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build

php artisan migrate --force
php artisan db:seed --class=RulePresetSeeder --force   # idempotent

php artisan optimize          # config + route + view + event caches

# Pick up the new code in the long-lived processes.
php artisan queue:restart
php artisan reverb:restart

php artisan up
```

Two things people forget:

- **`queue:restart` and `reverb:restart`.** Both processes hold the old code in memory.
  Without a restart, a deploy appears to do nothing to gameplay behaviour.
- **`optimize` after `migrate`, not before.** Config caching reads `.env`; run it once
  everything else has settled.

`reverb:restart` drops every open WebSocket. Clients reconnect automatically and the
heartbeat catches any state they missed, so a deploy mid-game is survivable — but
players will see the "Reconnecting…" badge flicker. Deploy between games if you can.

---

## 7. Behind a load balancer / multiple app nodes

### Trust the proxy

Rate limits key on `$request->ip()`. Behind a proxy every request looks like it comes
from the load balancer, so one player can exhaust the limit for everybody. Configure
the trusted proxies in `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->trustProxies(at: [
        '10.0.0.0/8',          // your load balancer's subnet — not '*' unless the
    ]);                        // app is genuinely unreachable except through it

    $middleware->web(append: [
        HandleInertiaRequests::class,
        AddLinkHeadersForPreloadedAssets::class,
    ]);
})
```

Use `at: '*'` only when nothing can reach the app directly, otherwise clients can spoof
`X-Forwarded-For` and bypass the limits entirely.

### Shared session store

Player identity is mirrored into the session (`follow_suit.players`), so sticky sessions
alone are not enough if a node dies mid-game. Use `SESSION_DRIVER=redis` (or `database`)
so any node can resolve any player.

The session is a convenience, not the source of truth — the browser also holds the token
in local storage and sends it as `X-Player-Token`, so a player can always recover. But a
shared store avoids the "please rejoin" screen.

### Scaling Reverb

A single Reverb process handles thousands of connections. Only when you run **more than
one** does it need Redis to fan broadcasts between them:

```ini
REVERB_SCALING_ENABLED=true
REDIS_HOST=your-redis-host
REDIS_PORT=6379
REDIS_PASSWORD=...
```

Without this, two Reverb nodes each see half your players and cards appear to vanish.
You'll also want sticky sessions at the load balancer so a client stays pinned to one
Reverb node for the life of its connection.

### File descriptors

Each WebSocket is an open file descriptor. Raise the limit for the Reverb process:

```ini
; in the [program:followsuit-reverb] block
minfds=10000
```

and system-wide in `/etc/security/limits.conf`:

```
www-data soft nofile 10000
www-data hard nofile 10000
```

---

## 8. Monitoring

### Health checks

- `GET /up` — Laravel's built-in health endpoint. Point your uptime monitor here.
- **Queue depth** — the metric that matters most. A growing queue means turns are
  lagging:

  ```bash
  php artisan queue:monitor default --max=100
  ```

- **Reverb** — check the process is up and the port is listening:

  ```bash
  supervisorctl status followsuit-reverb
  ss -tlnp | grep 8080
  ```

### What to alert on

| Signal | Why it matters |
| --- | --- |
| Queue depth > 100 for 2 min | Broadcasts and turn timers are backing up; the game feels broken |
| `failed_jobs` count rising | A move or timer is erroring; check `php artisan queue:failed` |
| Reverb process restarts | Every restart drops all connections |
| `games` rows with `status = in_progress` and `expires_at` in the past | The scheduler is not running |

That last one is a good canary — it should always be zero if cron is healthy:

```sql
SELECT COUNT(*) FROM games
WHERE status = 'in_progress' AND expires_at < NOW();
```

---

## 9. Security checklist

Most of this is already enforced in code (see `docs/architecture.md`), but verify at the
infrastructure level:

- [ ] `APP_DEBUG=false` — otherwise stack traces leak the deck order and player hands
- [ ] HTTPS everywhere, including `wss://` for Reverb
- [ ] `SESSION_SECURE_COOKIE=true`
- [ ] `REVERB_APP_SECRET` and `APP_KEY` are strong and not in version control
- [ ] Database not reachable from the internet
- [ ] `TrustProxies` scoped to your actual proxy subnet (§7)
- [ ] `storage/` and `bootstrap/cache` writable by `www-data`, nothing else
- [ ] `.env` mode `600`, owned by the deploy user

Already handled in application code, no action needed:

- Player tokens stored only as SHA-256 hashes
- Private channels authorised per player; nobody can subscribe to another hand
- The shuffled deck order never leaves the server
- Every move re-validated inside a row-locked transaction with a state-version check
- Nicknames and punishment text stripped of markup
- Rate limits on create, join and moves

---

## 10. Data growth and backups

Each game writes ~52 `game_cards` rows plus one `game_actions` row per event. A busy
evening of 100 games is roughly 5,000 card rows and a few thousand action rows — small,
but it accumulates.

`games:cleanup --purge` runs nightly and deletes completed/cancelled games older than
`FOLLOW_SUIT_PURGE_AFTER_DAYS`. Deletes cascade to players, cards, actions and rounds,
so nothing is orphaned.

Back up nightly, before the purge runs:

```bash
0 3 * * * mysqldump --single-transaction followsuitdb | gzip > /backups/followsuit-$(date +\%F).sql.gz
```

There is no user-generated content beyond nicknames and punishment text, so the recovery
point objective can be relaxed — the worst case is losing games in progress.

---

## 11. Rollback

```bash
cd /var/www/followsuit
php artisan down
git checkout <previous-tag>
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan optimize
php artisan queue:restart && php artisan reverb:restart
php artisan up
```

Migrations in this app are additive, so a code rollback rarely needs a schema rollback.
If one does:

```bash
php artisan migrate:rollback --step=1
```

Check `migrate:status` first, and remember that rolling back
`add_rematch_game_id_to_games_table` or `create_rule_presets_table` drops real data.

---

## 12. Troubleshooting

| Symptom | Likely cause |
| --- | --- |
| Invitation links point at the wrong domain | `APP_URL` wrong, or `config:cache` run before `.env` was correct |
| Lobby updates only every ~20 seconds | Queue worker not running, or Reverb unreachable — the app is falling back to the heartbeat |
| "Reconnecting…" badge never turns green | `REVERB_HOST`/`PORT`/`SCHEME` describe the wrong endpoint, or nginx is not proxying `/app` |
| Mixed-content error in the browser console | Page is HTTPS but `REVERB_SCHEME=http`; set it to `https` **and rebuild assets** |
| Turn timer never fires | Queue worker not running — `EnforceTurnTimeout` is a delayed job |
| Games never expire; disconnects never noticed | Scheduler not running |
| Every player shares one rate limit | `TrustProxies` not configured behind the load balancer |
| 419 on the broadcast auth request | CSRF token missing; check the `<meta name="csrf-token">` tag survives your HTML minifier |
| Cards vanish for half the players | Two Reverb nodes without `REVERB_SCALING_ENABLED=true` |
| Changed a `VITE_*` var, nothing happened | Those are compiled into the bundle; run `npm run build` |

### Quick diagnosis

```bash
php artisan about                       # drivers, cache status, versions
php artisan queue:monitor default       # is work backing up?
php artisan queue:failed                # are jobs erroring?
php artisan schedule:list               # is cleanup registered?
supervisorctl status                    # are all three processes up?
tail -f storage/logs/laravel.log
```
