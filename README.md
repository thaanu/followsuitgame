# Follow Suit

A real-time, mobile-first multiplayer card game for 2–4 players. One person creates a
room, shares a link or QR code, and everybody plays in the browser — no accounts, no
app to install.

Built with Laravel 13, Vue 3, Inertia v3, Tailwind v4, MySQL and Laravel Reverb.

- [Game rules](docs/game-rules.md)
- [Architecture](docs/architecture.md)
- [Production deployment](docs/deployment.md)

---

## Requirements

- PHP 8.3+
- Composer
- Node 20+
- MySQL 8 (or MariaDB 10.6+)

## Installation

```bash
git clone <repository> followsuitgame
cd followsuitgame

composer install
npm install

cp .env.example .env
php artisan key:generate
```

## Environment configuration

Set the database and WebSocket variables in `.env`.

```ini
APP_NAME="Follow Suit"
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=followsuitdb
DB_USERNAME=root
DB_PASSWORD=

QUEUE_CONNECTION=database
BROADCAST_CONNECTION=reverb
```

Game-specific settings live in `config/followsuit.php` and can all be overridden from
`.env`:

| Variable | Default | Meaning |
| --- | --- | --- |
| `FOLLOW_SUIT_GAME_LIFETIME_HOURS` | `24` | How long before an unfinished game is auto-cancelled |
| `FOLLOW_SUIT_PURGE_AFTER_DAYS` | `7` | How long finished games are kept |
| `FOLLOW_SUIT_HEARTBEAT_SECONDS` | `20` | How often the browser pings that it is still there |
| `FOLLOW_SUIT_PRESENCE_TIMEOUT_SECONDS` | `60` | Silence after which a player counts as disconnected |
| `FOLLOW_SUIT_RATE_CREATE` | `10` | Game creations per minute, per IP |
| `FOLLOW_SUIT_RATE_JOIN` | `20` | Join attempts per minute, per IP |
| `FOLLOW_SUIT_RATE_MOVES` | `120` | Moves per minute, per player |

## Database setup

Create the database, then run the migrations and the rule-preset seeder:

```bash
mysql -u root -e "CREATE DATABASE followsuitdb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

php artisan migrate
php artisan db:seed
```

`db:seed` writes the Classic and Quick presets into the `rule_presets` table. It is
idempotent, so it is safe to run again after an upgrade.

> Never run `migrate:fresh` against a database with live games — it drops every table.

## WebSocket setup

Real-time play uses [Laravel Reverb](https://reverb.laravel.com). The install has
already been done; you only need credentials in `.env`:

```ini
REVERB_APP_ID=123456
REVERB_APP_KEY=your-key
REVERB_APP_SECRET=your-secret
REVERB_HOST="localhost"
REVERB_PORT=8080
REVERB_SCHEME=http
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

Start the server with:

```bash
php artisan reverb:start
```

The two channels are both private:

- `private-game.{gamePublicId}` — public table state for everybody in the game
- `private-game.{gamePublicId}.player.{playerPublicId}` — one player's hand

Authorisation runs through the `player` guard (registered in `AppServiceProvider`),
which identifies the subscriber from the `X-Player-Token` header Echo sends. A player
can only ever subscribe to their own hand channel.

If the browser cannot reach Reverb, the game still works: a heartbeat every
`FOLLOW_SUIT_HEARTBEAT_SECONDS` notices when the state version has moved on and
refetches. Real-time is an upgrade, not a dependency.

## Queue setup

Broadcasts, the turn timer and the disconnection policy all run on the queue.

```bash
php artisan queue:work
```

The scheduler runs the cleanup command; in production add the usual single cron entry:

```
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

`games:cleanup` runs every minute (cancel expired games, mark silent players
disconnected) and `games:cleanup --purge` runs nightly to delete old records. You can
always run it by hand:

```bash
php artisan games:cleanup
php artisan games:cleanup --purge
```

## Starting the development servers

Four processes: the app, Vite, the queue worker and Reverb.

```bash
php artisan serve            # http://localhost:8000
npm run dev
php artisan queue:work
php artisan reverb:start
```

For a production-like check, build the assets instead of running Vite:

```bash
npm run build
```

## Running tests

```bash
php artisan test                    # everything
php artisan test --compact
php artisan test --filter=RuleEngine
```

Tests run against an in-memory SQLite database and never rely on the shuffle. The
helpers in `tests/Pest.php` deal exact hands:

```php
['game' => $game, 'players' => $players] = playing([
    ['7H', '8H'],   // seat 1
    ['2S', '3S'],   // seat 2
]);
```

The full quality gate — Pint, PHPStan level 7, ESLint, Prettier, `vue-tsc` and the
test suite — is:

```bash
composer test        # Pint + PHPStan + Pest
npm run lint:check && npm run format:check && npm run types:check
```

## Creating a game

1. Open `/games/create`.
2. Enter a nickname, an optional game name, and the maximum number of players (2–4).
3. Pick a preset — Classic Follow Suit or Quick Game — or open **Show all rule
   settings** and configure everything yourself.
4. Optionally set a punishment for whoever finishes worst, and confirm that everybody
   should agree to it.
5. Create the game. You land in the lobby with a game code, an invitation link, a
   share button and a QR code.
6. Start once at least two players have joined, every guest is ready, and — if a
   punishment is set — everybody has agreed to it.

## Joining a game

Open the invitation link, or go to `/games/join` and type the six-character code.
Choose a nickname (duplicates within the same game are refused) and you are seated.

Your browser is given a random 64-character token, stored in local storage and mirrored
into the session. The server only ever stores its SHA-256 hash. Refreshing, closing the
tab or losing signal is fine — reopening the link puts you straight back in your seat
with your hand intact. `/games/{game}/reconnect` shows the reconnection screen
explicitly.

## Adding a new rule

Rules are stored as a single JSON document on `games.rules` and hydrated into the
immutable `App\Game\Data\RuleConfiguration`. Adding one takes four steps and no
migration:

1. **Add an enum** in `app/Enums` for the new setting's values, with a `label()`
   method — for example `app/Enums/NextLeadRule.php`.
2. **Extend `RuleConfiguration`**: add the constructor property, a default in
   `defaults()`, hydration in `fromArray()`, serialisation in `toArray()`, and a row in
   `summary()` so it shows up in the lobby and table rule panels.
3. **Use it in `RuleEngine`** (or the relevant manager) — that is the only place rule
   decisions are made.
4. **Expose it to the UI**: add the options to `GameController::ruleOptions()`,
   validation to `ValidatesGameSettings::settingsRules()`, and a field to
   `resources/js/components/game/RulesForm.vue`.

Old rule documents keep working: `fromArray()` falls back to the defaults for missing
keys and discards unknown ones.

## Adding a new special-card effect

1. Add a case to `App\Enums\SpecialCardEffect`, with a `label()` and a `trigger()`
   returning the `CardRank` that fires it (or `null` if the rank is configurable).
2. Write a handler in `app/Game/Effects` implementing `SpecialCardEffectHandler`. It
   receives an `EffectContext` and records what should happen — change the active suit,
   reverse the direction, add `extraSkips`, set `grantsExtraTurn`, set `clearsPile`, or
   push an activity message.
3. Register it in `SpecialCardEffectManager::$handlers`.
4. Add a default (`false`) to `RuleConfiguration::defaults()` under
   `special_cards.effects`.

The effect then appears automatically in the rule form, because the toggles are
generated from `SpecialCardEffect::cases()`.

```php
// app/Game/Effects/DoubleTurnEffect.php
class DoubleTurnEffect implements SpecialCardEffectHandler
{
    public function apply(EffectContext $context): void
    {
        $context->grantsExtraTurn = true;
        $context->message("{$context->player->nickname} goes again.");
    }
}
```

## Deployment

Full walkthrough in **[docs/deployment.md](docs/deployment.md)** — Laravel Cloud, a
VPS with nginx and Supervisor, or Pusher instead of Reverb.

The short version: this app needs three long-lived processes in production, and none of
them are optional.

| Process | What breaks without it |
| --- | --- |
| Reverb | No live updates; the browser falls back to a ~20s heartbeat poll |
| Queue worker | Broadcasts never send, **and the turn timer and disconnection policy never fire** |
| Scheduler cron | Abandoned games are never cleaned up; disconnects go unnoticed |

Also make sure `APP_URL` matches your public HTTPS URL — invitation links and QR codes
are generated from it server-side, so a wrong value means nobody can join.

## Project layout

```
app/
├── Enums/                 Stable internal values — status, suits, ranks, every rule option
├── Game/
│   ├── Data/              RuleConfiguration, PunishmentConfiguration, MoveResult, Standing
│   ├── Effects/           One class per special card effect
│   ├── Exceptions/        GameException, with a stable reason code per case
│   ├── Services/          The game domain: RuleEngine, GameplayService, TurnManager, …
│   └── Support/           Card codes, game codes, player tokens, activity messages
├── Events/                Broadcast events (public table + private hand)
├── Http/                  Controllers, form requests, the player-resolving middleware
├── Jobs/                  Turn timeout and disconnection policy
└── Models/                Game, GamePlayer, GameCard, GameAction, GameRound

resources/js/
├── components/game/       Cards, player panels, rule form, punishment form, QR, timer
├── components/ui/         Buttons, panels, form fields
├── composables/           Echo channel, heartbeat, countdown, sound, player token
├── layouts/               AppLayout
└── pages/                 Welcome, Rules, Games/{Create,Join,Lobby,Table,Results,…}
```

## Assumptions made in v1

Documented in full in [docs/architecture.md](docs/architecture.md#7-assumptions).

- No accounts. Identity is an opaque browser token; the server stores only its hash.
- One browser can hold seats in several games at once; the session keeps a map of
  `game public_id => token`.
- Jokers can always be played. When the "Joker acts as any suit" effect is on, the
  player also nominates the new active suit.
- Deals that leave a remainder keep it as a draw pile, which is what the "draw a
  configurable number of cards" action draws from. If the deck runs dry, that action
  degrades to collecting the pile so a turn always resolves.
- Games expire 24 hours after creation.
