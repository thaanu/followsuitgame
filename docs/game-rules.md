# Follow Suit — game rules

Follow Suit is a card-shedding game for two to four players. This document describes
the base game and every rule the host can change.

---

## The base game

1. Cards are dealt to each player.
2. The starting player leads any card. Its suit becomes the **active suit**.
3. Every player in turn must play a card of the active suit.
4. A player who cannot follow the active suit **collects the entire pile** into their
   hand, and leads the next pile.
5. Play continues until somebody meets the winning condition — by default, the first
   player to empty their hand wins.

Nothing above is hardcoded. Each numbered step maps to a setting below.

---

## Presets

### Classic Follow Suit

The default. The whole deck is dealt out evenly. Players must follow the active suit;
a player who cannot takes the entire pile into their hand and leads the next one. The
first player to empty their hand wins.

### Quick Game

Seven cards each, the rest becoming a draw pile. A player who cannot follow suit takes
the pile as **penalty cards** which leave play but still count against them. The player
with the fewest penalty cards wins.

### Custom

Every setting below, configured by the host.

---

## General settings

| Setting | Options |
| --- | --- |
| Number of players | 2, 3 or 4 |
| Deck | Standard 52-card deck (no Jokers) · Standard deck with 2 Jokers |
| Dealing | Deal all cards evenly · Deal a fixed number of cards (1–13) |
| Starting player | The host · A random player · The holder of a specific card |
| Turn direction | Clockwise · Counterclockwise |

When cards are dealt evenly and the deck does not divide exactly, the remainder stays
in the deck. When a fixed number is dealt, everything else stays in the deck. That
remainder is the draw pile.

---

## Follow-suit rules

**When a player cannot follow the active suit**, one of:

- **Collect the entire pile.** Every card in the middle goes to that player.
- **Draw cards from the deck** (1–5). If a drawn card can be played, the turn stays
  with them; otherwise it passes on. If the deck is empty, they collect the pile
  instead, so the turn always resolves.
- **Skip the turn.** The pile stays where it is and play moves on.

**Voluntary collect.** Optionally, a player may take the pile even when they *could*
follow suit. Off by default. A voluntary collect does not count as a failure to follow
suit.

**Who leads the next pile**, one of:

- The player who collected the pile (default)
- The next player in turn order
- The player who last successfully followed suit

If the chosen player has run out of cards, the lead passes to the next seat that still
has some.

---

## Pile rules

**A pile ends**, one of:

- When a player cannot follow suit (default)
- After every player has played one card
- After a set number of cards (2–20)
- When a special card is played

For the count-based endings, the completed pile goes to the player who played the
winning card — see *Card ranking* below.

**Collected cards go**, one of:

- Into the player's hand (default) — the classic penalty, your hand grows
- Into the player's penalty pile — visible, counted, but out of their hand
- Counted only — the cards leave play entirely but still count against the player

---

## Card ranking

| Setting | Options |
| --- | --- |
| Ace value | Ace is high (14) · Ace is low (1) · Card rank does not matter |
| A completed pile goes to | The highest card of the active suit · The lowest card |

Ranking only matters when piles end by count. When rank does not matter, a completed
pile goes to whoever led it.

---

## Special cards

All optional, all off by default. Enable the group, then pick individual effects.

| Effect | Behaviour |
| --- | --- |
| **Ace changes the active suit** | An Ace can always be played, and the player nominates the new suit everybody must follow. |
| **King reverses the turn direction** | Play turns around. With two players, that means the same player goes again. |
| **Queen skips the next player** | The turn jumps a seat. |
| **Jack allows another turn** | The same player plays again immediately. |
| **Joker acts as any suit** | The player nominates the active suit. Requires the Joker deck. |
| **A chosen rank clears the pile** | The pile is swept off the table. Nobody collects it and nobody is penalised. The host picks which rank. |

A Joker can always be played whatever the active suit, because it has no suit of its
own. Without the "acts as any suit" effect it simply leaves the active suit unchanged.

---

## Winning

| Condition | The game ends when |
| --- | --- |
| First player to empty their hand wins (default) | Anybody runs out of cards |
| Last remaining player loses | Only one player still holds cards |
| Lowest number of collected cards wins | Fewer than two players still hold cards |
| Lowest number of collected piles wins | Fewer than two players still hold cards |
| Play until only one player still has cards | Only one player still holds cards |
| Play a set number of rounds (1–10) | The last round finishes; hands are redealt between rounds and collected counts accumulate |

Players who go out are ranked by the order in which they did so. Anybody still holding
cards ranks behind them, fewest cards first.

---

## Tie-breaking

When two players are level on the primary condition:

- Player with fewer collected cards wins (default)
- Player with fewer collected piles wins
- Shared result — the tie stands and both share the position
- Play a tie-breaker round

---

## Turn timer

Off by default. When on, the host picks 15, 30, 45, 60 seconds or a custom length
(5–600). The deadline is set by the server and shown to everybody; the browser clock is
only used to draw the countdown, so a slow or fast device cannot gain or lose time.

**When the time runs out**, one of:

- Play a random legal card (default)
- Collect the pile
- Skip the turn
- The host decides — the turn is left where it is

---

## Disconnections

Every player has a configurable grace period (10–600 seconds, 60 by default) to get
back before anything happens. Reconnecting restores their identity, their hand and the
current table state.

**If they stay away**, one of:

- Pause the game until they return
- Skip the player after the grace period (default)
- Automatically play a valid card for them
- The host decides

---

## Punishments

The host may attach an optional punishment for whoever finishes worst. It is shown to
every player in the lobby, and **each player has to agree to it before the game can
start**.

**Who receives it**, one of: most cards collected · most piles collected · last player
to empty their hand · lowest final position · the host decides afterwards.

A punishment has a title, an optional description, an optional emoji, and an optional
severity of Fun, Mild or Challenging.

When two players tie for worst, the configured tie-break rule settles it. Under a
shared result, or when a tie-breaker round was configured but not played, the results
screen says so and leaves the final call to the group.

> Punishments must be safe, legal, respectful and agreed by everyone playing. The
> built-in suggestions are all harmless — buying the snacks, making the tea, singing a
> song, picking the next game. Nothing dangerous, humiliating, harassing, illegal,
> coercive, discriminatory, sexual, or involving alcohol or other substances.

---

## Fair play

- Every move is validated on the server. The browser's opinion about what is legal is
  never trusted.
- Your hand is sent only to you, on a private channel keyed to your own player id.
  Opponents are shown as card counts.
- The shuffled deck order never leaves the server, so there is nothing useful to find
  in the page source or the network tab.
- Each move carries the state version it was made against, so a duplicate submission
  or a stale retry is rejected rather than played twice.
