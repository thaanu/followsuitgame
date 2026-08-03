# Follow Suit — Architecture

This document describes the database schema, game-state model, rule configuration
structure, backend service architecture and WebSocket event flow for the Follow Suit
multiplayer card game.

---

## 1. Database schema

All gameplay tables are keyed by an internal auto-incrementing `id` and expose an
opaque `public_id` (ULID) where the identifier is shared with the browser. The
original deck order is never exposed to clients.

### `games`

| Column | Type | Notes |
| --- | --- | --- |
| `id` | `bigIncrements` | |
| `public_id` | `char(26)` unique | ULID used in URLs and channel names |
| `game_code` | `char(6)` unique | Human friendly code, e.g. `ABCD12` |
| `invitation_token` | `char(64)` unique | Random token embedded in invite URLs |
| `name` | `string` nullable | Optional game name |
| `host_player_id` | `foreignId` nullable | FK → `game_players.id` (nullable, set after host row exists) |
| `status` | `string` | `App\Enums\GameStatus` |
| `maximum_players` | `unsignedTinyInteger` | 2–4 |
| `rule_preset` | `string` | `App\Enums\RulePreset` |
| `rules` | `json` | Serialised `App\Game\Data\RuleConfiguration` |
| `punishment_enabled` | `boolean` | |
| `punishment` | `json` nullable | Serialised `App\Game\Data\PunishmentConfiguration` |
| `current_player_id` | `foreignId` nullable | FK → `game_players.id` |
| `starting_player_id` | `foreignId` nullable | FK → `game_players.id` |
| `active_suit` | `string` nullable | `App\Enums\CardSuit` |
| `direction` | `string` | `App\Enums\TurnDirection` |
| `round_number` | `unsignedInteger` | |
| `turn_number` | `unsignedInteger` | |
| `state_version` | `unsignedBigInteger` | Optimistic concurrency token |
| `turn_deadline_at` | `datetime` nullable | Server authoritative turn timer deadline |
| `pending_extra_turn` | `boolean` | Set by the Jack "another turn" effect |
| `started_at` / `completed_at` / `cancelled_at` / `expires_at` | `datetime` nullable | |
| `timestamps` | | |

Indexes: `public_id`, `game_code`, `invitation_token`, `status`, `(status, expires_at)`.

### `game_players`

| Column | Type | Notes |
| --- | --- | --- |
| `id` | `bigIncrements` | |
| `public_id` | `char(26)` unique | Exposed to clients instead of `id` |
| `game_id` | `foreignId` → `games.id` cascade delete | |
| `nickname` | `string` | |
| `secure_token_hash` | `char(64)` indexed | SHA-256 of the player's browser token |
| `seat_number` | `unsignedTinyInteger` | Unique per game; drives turn order |
| `is_host` | `boolean` | |
| `is_ready` | `boolean` | |
| `punishment_acknowledged_at` | `datetime` nullable | |
| `connection_status` | `string` | `App\Enums\ConnectionStatus` |
| `last_seen_at` | `datetime` nullable | |
| `disconnected_at` | `datetime` nullable | Grace period anchor |
| `turn_timeout_count` | `unsignedInteger` | |
| `cards_collected_count` | `unsignedInteger` | |
| `piles_collected_count` | `unsignedInteger` | |
| `failed_follow_suit_count` | `unsignedInteger` | |
| `finishing_position` | `unsignedTinyInteger` nullable | |
| `joined_at` / `left_at` | `datetime` nullable | |
| `timestamps` | | |

Unique: `(game_id, seat_number)`, `(game_id, nickname)`. Index: `(game_id, secure_token_hash)`.

### `game_cards`

| Column | Type | Notes |
| --- | --- | --- |
| `id` | `bigIncrements` | |
| `game_id` | `foreignId` cascade delete | |
| `deck_number` | `unsignedTinyInteger` | Multi-deck support |
| `suit` | `string` | `App\Enums\CardSuit` |
| `rank` | `string` | `App\Enums\CardRank` |
| `rank_value` | `unsignedTinyInteger` | Resolved from the ace-high/ace-low rule at deal time |
| `card_code` | `string` | e.g. `7H`, `KS`, `JOKER-1` |
| `location` | `string` | `App\Enums\CardLocation` |
| `player_id` | `foreignId` nullable → `game_players.id` | |
| `pile_position` | `unsignedInteger` nullable | Order within the active pile |
| `original_deal_position` | `unsignedInteger` nullable | Shuffled deck index; server-only |
| `timestamps` | | |

Indexes: `(game_id, location)`, `(game_id, player_id, location)`, `(game_id, location, pile_position)`.

### `game_actions`

Append-only activity/audit log. Columns: `id`, `game_id`, `player_id` (nullable),
`action_type` (`App\Enums\GameActionType`), `card_id` (nullable), `turn_number`,
`state_version`, `public_payload` (json), `private_payload` (json, nullable),
`created_at`. Index: `(game_id, id)`, `(game_id, turn_number)`.

An `idempotency_key` unique-per-game column guards against duplicate move submissions.

### `rule_presets`

The two published presets are seeded here so hosts can save their own configurations
in a later version without a schema change. Columns: `id`, `slug` (unique), `name`,
`description`, `rules` (json), `is_built_in`, `sort_order`, timestamps. The enum in
`App\Enums\RulePreset` remains the fallback when the table has not been seeded.

### `game_rounds`

Columns: `id`, `game_id`, `round_number`, `starting_player_id`, `winner_player_id`,
`started_at`, `completed_at`, `result` (json), timestamps.
Unique: `(game_id, round_number)`.

---

## 2. Game-state model

The server is the single source of truth. State is split into three projections:

1. **Public state** — broadcast to every player on `game.{publicId}`:
   status, current player, active suit, direction, round/turn number, `state_version`,
   the visible active pile, per-player card counts and collected counts, turn deadline,
   and the recent activity feed.
2. **Private state** — sent only to the owning player on `game.{publicId}.player.{playerPublicId}`:
   the player's full hand plus the set of legal card ids for the current turn.
3. **Server-only state** — never leaves the server: `original_deal_position`, the
   remaining deck order, and every other player's hand contents.

`GameStatePresenter` is the only class permitted to build client payloads, which
guarantees hidden information cannot leak through an ad-hoc controller response.

### Concurrency

Every mutating action runs inside a `DB::transaction` that begins with
`Game::whereKey($id)->lockForUpdate()->first()`. Requests carry the
`state_version` they were rendered against; a mismatch aborts with HTTP 409 so a
stale client can never replay a turn. `state_version` increments on every committed
mutation and is also the deduplication anchor for the activity log.

---

## 3. Rule configuration structure

Rules are stored as a single JSON document on `games.rules` and hydrated into the
immutable `App\Game\Data\RuleConfiguration` value object. Every rule value is backed
by a PHP enum, so new options are added by extending an enum plus the defaults array —
no schema migration is required.

```jsonc
{
  "general": {
    "deck_type": "standard_52",          // DeckType
    "deal_method": "all_evenly",         // DealMethod
    "fixed_cards_per_player": 7,
    "starting_player_method": "host",    // StartingPlayerMethod
    "starting_card_code": "AS",          // used by starting_player_method = specific_card
    "turn_direction": "clockwise"        // TurnDirection
  },
  "follow_suit": {
    "unable_to_follow_action": "collect_pile", // UnableToFollowAction
    "draw_card_count": 1,
    "allow_voluntary_collect": false,
    "next_lead_rule": "collector"        // NextLeadRule
  },
  "pile": {
    "end_condition": "cannot_follow_suit", // PileEndCondition
    "fixed_card_count": 4,
    "collected_cards_destination": "hand"  // CollectedCardsDestination
  },
  "ranking": {
    "ace_rule": "ace_high",              // AceRule
    "round_winner": "highest_card"       // RoundWinner
  },
  "special_cards": {
    "enabled": false,
    "effects": {                          // SpecialCardEffect => bool
      "ace_changes_suit": false,
      "king_reverses_direction": false,
      "queen_skips_next": false,
      "jack_extra_turn": false,
      "joker_wild_suit": false,
      "clear_pile_rank": null             // CardRank|null
    }
  },
  "winning": {
    "condition": "first_to_empty_hand",  // WinningCondition
    "round_limit": 3,
    "tie_break": "fewer_collected_cards" // TieBreakRule
  },
  "timer": {
    "enabled": false,
    "seconds": 30,
    "timeout_action": "play_random_legal" // TimeoutAction
  },
  "connection": {
    "grace_period_seconds": 60,
    "disconnect_behaviour": "skip_after_timeout" // DisconnectBehaviour
  }
}
```

`RuleConfiguration::preset(RulePreset)` returns the Classic and Quick presets; the
Custom preset starts from Classic and applies the host's overrides. Unknown keys are
discarded on hydration and missing keys fall back to defaults, so an older stored
document keeps working after new rules ship.

---

## 4. Backend service architecture

Controllers only validate, authorize and delegate. All gameplay lives in
`app/Game`.

```
app/Game
├── Data/                       Immutable value objects
│   ├── RuleConfiguration       Typed accessor over the rules JSON
│   ├── PunishmentConfiguration Typed accessor over the punishment JSON
│   ├── MoveResult              What a committed move changed
│   └── Standing                One row of the final table
├── Effects/                    One handler per special card effect
├── Exceptions/
│   └── GameException           A reason code per rule or lifecycle violation
├── Services/
│   ├── ActivityLogger          Append-only activity feed
│   ├── GameBroadcaster         State change to broadcast event
│   ├── GameplayService         Turn processing, the only writer of game state
│   ├── GameCreationService     Codes, tokens, host seat, rules persistence
│   ├── GameJoiningService      Token validation, nickname rules, reconnect-vs-join
│   ├── GameStartService        Lobby lock, deal, starting player, GameStarted
│   ├── DeckService             Builds and shuffles a deck (CSPRNG, injectable order)
│   ├── CardDealingService      Writes game_cards rows from a deck order
│   ├── RuleEngine              Pure decisions driven by RuleConfiguration
│   ├── MoveValidationService   Ownership / turn / legality / version checks
│   ├── TurnManager             Advances the seat pointer, applies direction & skips
│   ├── PileManager             Pile lifecycle and collection bookkeeping
│   ├── SpecialCardEffectManager Registry of card effects, keyed by enum
│   ├── WinningConditionService Completion detection and standings
│   ├── PunishmentResolver      Chooses the punished player and explains why
│   ├── GameStatePresenter      Public / private payload construction
│   └── ReconnectionService     Token→player resolution, presence bookkeeping
└── Support/
    ├── ActivityMessage         Human readable activity strings
    ├── CardCode                Card code parsing helpers
    ├── GameCode                Six-character room codes
    └── PlayerToken             Browser token generation and hashing
```

### Authorisation

`App\Policies\GamePolicy` holds every "who may do this" rule, with a `GamePlayer` as
the acting identity rather than a `User`. Controllers call
`authorizePlayer($request, $ability, $game)`, which runs the policy and translates a
denial into the `GameException` reason code the frontend knows how to render.

| Ability | Allowed to |
| --- | --- |
| `view` | Any seated player who has not left |
| `act` | The player whose turn it is, in a running, unpaused game |
| `start`, `updateSettings`, `removePlayer` | The host, while the game is still in the lobby |
| `cancel` | The host, at any time before the game finishes |
| `rematch` | The host of a finished game |

The policy is a gate, not the last word: `GameplayService` re-runs every check inside
the locked transaction that commits the move, so authorisation cannot be raced.

`RuleEngine` is the heart of the system. Given a `Game` (with its hydrated
`RuleConfiguration`) it answers:

* `legalCards(GamePlayer): Collection<GameCard>`
* `canFollowSuit(GamePlayer): bool`
* `unableToFollowAction(): UnableToFollowAction`
* `pileShouldEnd(Game, int $cardsInPile): bool`
* `nextLeadPlayer(...): GamePlayer`
* `specialEffectsFor(GameCard): array<SpecialCardEffect>`
* `isComplete(Game): bool`
* `standings(Game): array<Standing>`

`SpecialCardEffectManager` resolves each enabled `SpecialCardEffect` to a small
handler class, so a new effect is one enum case plus one handler.

---

## 5. WebSocket event flow

Two channel types per game, both private and authorised by the player's browser token
(see `routes/channels.php` and the `player` auth guard).

```
private-game.{gamePublicId}                 public state, everyone in the game
private-game.{gamePublicId}.player.{playerPublicId}   private hand, one player only
```

Channel authorisation reads the `X-Player-Token` header sent by Echo, hashes it, and
matches it against `game_players.secure_token_hash` scoped to the game in the channel
name. A player can therefore only ever subscribe to their own private channel.

| Trigger | Event | Channel(s) | Payload |
| --- | --- | --- | --- |
| Player joins the lobby | `PlayerJoined` | public | lobby snapshot |
| Player leaves / is removed | `PlayerLeft` | public | lobby snapshot |
| Ready or acknowledgement toggled | `PlayerReadyStatusChanged` | public | lobby snapshot |
| Host edits rules | `GameSettingsUpdated` | public | rules + punishment summary |
| Host starts the game | `GameStarted` | public + each private | public state; each hand |
| Card played | `CardPlayed` | public + affected private | public state; new hand |
| Pile collected | `PileCollected` | public + affected private | public state; new hand |
| Turn advanced | `TurnChanged` | public | current player + deadline |
| Connection state changes | `PlayerDisconnected` / `PlayerReconnected` | public | player id + status |
| Player empties their hand | `PlayerFinished` | public | player + position |
| Game ends | `GameCompleted` | public | standings + punishment |
| Host cancels | `GameCancelled` | public | reason |

All broadcast events implement `ShouldBroadcast` and are dispatched onto the queue, so
HTTP responses are never blocked by the WebSocket server. Payloads are built by
`GameStatePresenter` and are deliberately small: the public payload never contains card
identities beyond the active pile, and private payloads are addressed to exactly one
player channel.

Queued work:

* `TurnTimeoutJob` — dispatched with a delay equal to the configured turn timer and
  guarded by the `state_version` it was scheduled against, so it becomes a no-op if the
  player moved in time.
* `HandleDisconnectionGracePeriodJob` — applies the configured disconnect behaviour once
  the grace period expires.
* `games:cleanup` — scheduled command that cancels abandoned games past `expires_at`.

---

## 6. Implementation phases

1. **Foundation** — schema, models, enums, rule configuration, game creation,
   invitations, join flow, lobby.
2. **Core engine** — deck, shuffle, deal, turn order, follow-suit validation, pile
   collection, winning condition.
3. **Real-time** — broadcast events, private channels, live lobby and table,
   connection tracking, reconnection.
4. **Configurable rules** — presets, alternative pile behaviour, starting player,
   special cards, turn timer, tie-breaking.
5. **Punishments and results** — punishment setup, acknowledgement, standings,
   punishment resolution, rematch.
6. **Polish** — responsive UI, animations, sound, security review, tests, cleanup.

---

## 7. Assumptions

* No user accounts in v1. Identity is a random 64-character browser token; the server
  stores only its SHA-256 hash. The token is mirrored into the Laravel session so a
  page navigation without JavaScript state still resolves the player.
* A browser may hold tokens for several games simultaneously; the session stores a map
  of `game public_id => token`.
* Jokers are only present when the deck type includes them, and only carry meaning when
  the `joker_wild_suit` effect is enabled; otherwise they are treated as suitless cards
  that can always be played.
* "Deal a fixed number of cards" leaves the remainder in the `deck` location, which is
  what the "draw a configurable number of cards" unable-to-follow action draws from. If
  the deck runs out, the action degrades to collecting the pile.
* Games expire 24 hours after creation; the cleanup command cancels them.
