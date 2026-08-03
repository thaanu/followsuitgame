# AI Agent Prompt: Build a “Follow Suit” Multiplayer Card Game

Build a production-ready, responsive web application for a multiplayer playing-card game called **Follow Suit**.

Use the following technology stack:

* Laravel
* Vue 3
* Inertia.js
* Tailwind CSS
* MySQL
* Laravel Reverb or another Laravel-compatible WebSocket solution for real-time gameplay
* Laravel queues where appropriate

The game should support 2–4 players.

The application must be mobile-friendly because most players will join and play using their phones.

## 1. Game Overview

Follow Suit is a card-shedding game.

A player creates a game room and shares an invitation link with other players. Other players can open the link, enter a nickname, and join the game.

The host configures the game rules before starting.

During gameplay:

1. The starting player plays any card.
2. The next player must play a card from the same suit.
3. If the next player cannot follow the active suit, that player collects the entire pile.
4. The game continues according to the configured rules.
5. The main objective is normally to get rid of all cards.
6. The player who collects the most cards or piles may receive an optional punishment set by the host.

Because different groups may play alternative versions, the rules must not be hardcoded. The host should be able to configure important rules from the game settings.

---

# 2. User Roles

## Host

The player who creates the game.

The host can:

* Create a game room.
* Choose a nickname.
* Configure game rules.
* Set an optional punishment.
* Share the invitation link.
* View players waiting in the lobby.
* Remove a player before the game starts.
* Start the game.
* Restart or create a rematch after the game ends.
* End or cancel the game.

## Guest Player

A player who joins through an invitation link.

The guest can:

* Enter a nickname.
* Join the game lobby.
* View the configured game rules.
* View the punishment, when one is configured.
* Mark themselves as ready.
* Leave the lobby before the game starts.
* Participate in the game after the host starts it.

No account registration should be required for the first version.

Use a secure player token stored in the browser session or local storage so that a player can reconnect to the same game after refreshing the page.

---

# 3. Game Creation

Create a “Create Game” page.

The host must enter:

* Host nickname
* Maximum players: 2, 3, or 4
* Game name, optional
* Rule configuration
* Optional punishment

After creating the game, generate:

* A unique game code
* A secure invitation token
* A shareable invitation URL
* A QR code for the invitation URL

Example invitation URL:

`https://example.com/games/join/ABCD12`

Provide buttons for:

* Copy invitation link
* Share invitation link using the Web Share API
* Display QR code

---

# 4. Configurable Game Rules

Create a settings interface that allows the host to configure game rules before starting.

Store the complete rule configuration as structured data so new rule options can be added later without redesigning the core game.

Include the following rule settings.

## General Settings

* Number of players: 2–4
* Deck type:

  * Standard 52-card deck
  * Standard deck without Jokers
  * Optional future support for multiple decks
* Deal method:

  * Deal all cards evenly
  * Deal a fixed number of cards
* Fixed number of cards per player, when applicable
* Starting player:

  * Host
  * Random player
  * Player holding a specific card
* Turn direction:

  * Clockwise
  * Counterclockwise

## Follow-Suit Rules

* Players must follow the suit of the first card in the active pile.
* When a player does not have the active suit:

  * Collect the entire pile immediately
  * Draw a configurable number of cards
  * Skip the turn
* Allow a player to voluntarily collect the pile even when they can follow suit:

  * Yes
  * No
* After collecting the pile, the next lead player is:

  * The player who collected the pile
  * The next player in turn order
  * The player who last successfully followed suit

## Pile Rules

Configure when a pile ends:

* When a player cannot follow suit
* After every player has played one card
* After a configurable number of cards
* When a special card is played

Configure what happens to collected cards:

* Add them to the player’s hand
* Move them to the player’s collected-pile score area
* Count the pile without returning cards to the hand

## Card Ranking Rules

Allow the host to configure:

* Ace high
* Ace low
* Card rank does not matter
* Highest card wins a completed round
* Lowest card wins a completed round

## Special Card Rules

Special card rules should be optional.

Allow the host to enable or disable effects such as:

* Ace changes the active suit.
* King reverses the turn direction.
* Queen skips the next player.
* Jack allows another turn.
* Joker acts as any suit.
* A specific rank clears the pile.

The special-card system should be designed so additional card effects can be introduced later.

## Winning Rules

Allow the host to choose the winning condition:

* First player to empty their hand wins.
* Last remaining player loses.
* Lowest number of collected cards wins.
* Lowest number of collected piles wins.
* Play until only one player still has cards.
* Play for a configured number of rounds.

## Tie-Breaking Rules

Allow the host to choose:

* Player with fewer collected cards wins.
* Player with fewer collected piles wins.
* Shared result.
* Play a tie-breaker round.

---

# 5. Rule Presets

Provide rule presets to make game creation easier.

Include:

## Classic Follow Suit

* Deal all cards evenly.
* Players must follow the active suit.
* A player who cannot follow suit collects the entire pile into their hand.
* The player who collects the pile starts the next pile.
* The first player to empty their hand wins.

## Quick Game

* Deal a fixed number of cards.
* A player who cannot follow suit collects the pile.
* Collected cards count as penalty cards but do not return to the hand.
* The player with the fewest penalty cards wins.

## Custom

The host manually configures all available settings.

The host should be able to save a custom configuration as a reusable preset in a later version.

---

# 6. Punishment Feature

Before starting the game, the host can optionally define a punishment.

The punishment applies to the player who finishes with the highest configured penalty.

Allow the host to select what determines the punished player:

* Most cards collected
* Most piles collected
* Last player to empty their hand
* Lowest final position
* Custom host decision after the game

The punishment form should contain:

* Punishment title
* Punishment description
* Optional emoji
* Optional severity:

  * Fun
  * Mild
  * Challenging
* Confirmation checkbox stating that all players should agree to the punishment

Examples:

* Buy snacks for everyone
* Sing a song
* Do ten push-ups
* Make tea for the group
* Post a funny status
* Pay for the next game-night refreshments

The punishment must be visible to all players in the lobby before they mark themselves ready.

Each player must acknowledge the punishment before the host can start the game.

Add a notice explaining that punishments should be safe, legal, respectful, and mutually agreed upon.

Do not provide built-in punishments involving:

* Physical danger
* Humiliation
* Harassment
* Illegal activity
* Financial coercion
* Alcohol or controlled substances
* Sexual activity
* Discrimination

At the end of the game, clearly display:

* The player receiving the punishment
* Why they received it
* The punishment title and description
* Whether there was a tie

When there is a tie, use the configured tie-breaking rule.

---

# 7. Lobby

After creating or joining a game, show a real-time lobby.

Display:

* Game name
* Game code
* Host name
* Player count
* Maximum player count
* Joined players
* Ready status
* Host badge
* Rule summary
* Punishment summary
* Invitation link
* QR code
* Game status

Each player should appear as a card containing:

* Nickname
* Avatar generated from initials or an emoji
* Host indicator
* Ready indicator
* Connected or disconnected indicator

The host can start the game only when:

* At least two players have joined.
* All non-host players are ready.
* All players have acknowledged the punishment when a punishment is enabled.
* The player count does not exceed the configured limit.

Lock all game settings after the game starts.

---

# 8. Joining a Game

When a user opens an invitation link:

1. Validate the game and invitation token.
2. Confirm that the game is still accepting players.
3. Ask the player to enter a nickname.
4. Prevent duplicate nicknames within the same game.
5. Create a secure player session.
6. Redirect the player to the lobby.

Handle these states:

* Invalid invitation
* Expired invitation
* Game already started
* Game full
* Game cancelled
* Player previously joined
* Player reconnecting
* Nickname already taken

When the player previously joined from the same browser, restore their player identity instead of creating a duplicate player.

---

# 9. Card Dealing

When the host starts the game:

1. Lock the lobby.
2. Create and shuffle the deck securely on the server.
3. Determine the starting player using the configured rule.
4. Deal cards according to the configured deal method.
5. Store the exact card order and deal results on the server.
6. Send each player only the cards belonging to that player.
7. Never expose another player’s full hand to the frontend.
8. Broadcast that the game has started.

Use cryptographically secure randomness where practical.

Represent each card with:

* Suit
* Rank
* Unique card identifier
* Deck identifier, for future multi-deck support

Suits:

* Hearts
* Diamonds
* Clubs
* Spades

Ranks:

* Ace
* 2–10
* Jack
* Queen
* King

---

# 10. Gameplay Interface

Create a responsive card-table interface.

Display:

* Current player
* Turn direction
* Active suit
* Cards in the current pile
* Number of cards held by each opponent
* Number of piles or cards collected by each player
* Current round
* Recent game activity
* Configured punishment summary
* Game rules button

For the current player:

* Display their complete hand.
* Group or sort cards by suit and rank.
* Highlight cards that can legally be played.
* Disable illegal cards.
* Allow the player to select and confirm a card.
* Show an action to collect the pile when required.
* Show a turn timer when enabled.

For opponents:

* Display card backs.
* Display only the number of cards in their hand.
* Never send hidden card details to unauthorized clients.

The layout should work well on:

* iPhone
* Android phones
* Tablets
* Desktop browsers

---

# 11. Turn Processing

All gameplay decisions must be validated by the server.

Do not trust the frontend to determine whether a move is legal.

When a player attempts to play a card, validate:

* The game is active.
* The player belongs to the game.
* It is the player’s turn.
* The player owns the selected card.
* The card follows the active suit when required.
* The card is valid under special-card rules.
* The move has not already been processed.
* The game version or turn version matches the latest server state.

Use database transactions and locking to prevent two actions from being processed for the same turn.

After a valid move:

1. Remove the card from the player’s hand.
2. Add it to the active pile.
3. Apply special-card effects.
4. Determine whether the pile continues or ends.
5. Determine the next player.
6. Check whether any player has met the winning condition.
7. Save an activity log.
8. Broadcast the updated public game state.
9. Send private hand updates only to the affected player.

Use an incrementing turn version or state version to avoid duplicate actions and race conditions.

---

# 12. Unable to Follow Suit

When it is a player’s turn, the server must determine whether the player owns at least one card from the active suit.

If the player has a matching suit:

* Only matching cards should normally be legal.
* Apply any configured exceptions.

If the player does not have a matching suit:

* Apply the configured unable-to-follow action.
* Show a clear message explaining what happens.
* Update the player’s hand, pile score, or turn state.
* Broadcast the result to all players.

When the rule requires the player to collect the pile:

1. Move all cards from the active pile to the appropriate player location.
2. Increment the player’s collected-pile count.
3. Increment the player’s collected-card count.
4. Clear the active pile.
5. Determine who starts the next pile.
6. Record the event in the activity log.

---

# 13. Reconnection and Disconnection

Players may temporarily lose internet access.

Implement reconnection support.

When a player disconnects:

* Mark them as disconnected.
* Keep their place in the game.
* Start a configurable reconnection grace period.
* Allow them to reconnect using their secure player token.

When they reconnect:

* Restore their identity.
* Restore their current hand.
* Restore the current game state.
* Restore pending turn actions.
* Mark them as connected.

Provide configurable disconnection behavior:

* Pause the game.
* Skip the disconnected player after a timeout.
* Automatically play a valid random card.
* Remove the player and convert them to an automated player.
* Allow the host to choose an action.

Do not expose private hand information during reconnection.

---

# 14. Turn Timer

Allow the host to optionally enable a turn timer.

Options:

* Disabled
* 15 seconds
* 30 seconds
* 45 seconds
* 60 seconds
* Custom duration

When the timer expires, apply the configured timeout action:

* Play a random legal card.
* Collect the pile.
* Skip the turn.
* Allow the host to decide.
* Remove the inactive player after repeated timeouts.

Display the timer consistently to all players using a server-authoritative deadline.

Do not rely entirely on a JavaScript countdown for turn expiration.

---

# 15. Game Completion

When the winning condition is reached:

1. Mark the game as completed.
2. Prevent further card actions.
3. Calculate the final standings.
4. Calculate collected cards and piles.
5. Determine the winner.
6. Determine the punished player when punishment is enabled.
7. Save the final result.
8. Broadcast the completed state.

Display a results page containing:

* Winner
* Full ranking
* Cards remaining for each player
* Cards collected by each player
* Piles collected by each player
* Number of turns played
* Number of times each player failed to follow suit
* Punished player
* Punishment details
* Game duration

Provide actions for:

* Play again with the same players
* Rematch with the same rules
* Change rules and create another game
* Copy results
* Leave the game

For a rematch, rotate or randomly select the starting player according to the configured rule.

---

# 16. Data Model

Create an appropriate normalized database structure.

Suggested tables:

## games

Fields may include:

* id
* public_id
* game_code
* invitation_token
* name
* host_player_id
* status
* maximum_players
* rule_preset
* rules JSON
* punishment_enabled
* punishment JSON
* current_player_id
* starting_player_id
* active_suit
* direction
* round_number
* turn_number
* state_version
* started_at
* completed_at
* cancelled_at
* expires_at
* timestamps

## game_players

Fields may include:

* id
* game_id
* nickname
* secure_token_hash
* seat_number
* is_host
* is_ready
* punishment_acknowledged_at
* connection_status
* last_seen_at
* turn_timeout_count
* cards_collected_count
* piles_collected_count
* failed_follow_suit_count
* finishing_position
* joined_at
* left_at
* timestamps

## game_cards

Fields may include:

* id
* game_id
* deck_number
* suit
* rank
* rank_value
* card_code
* location
* player_id
* pile_position
* original_deal_position
* timestamps

Possible card locations:

* deck
* player_hand
* active_pile
* collected_cards
* discarded
* removed

## game_actions

Fields may include:

* id
* game_id
* player_id
* action_type
* card_id
* turn_number
* state_version
* public_payload JSON
* private_payload JSON
* created_at

Example action types:

* game_created
* player_joined
* player_ready
* game_started
* card_played
* pile_collected
* turn_skipped
* direction_reversed
* suit_changed
* player_disconnected
* player_reconnected
* player_finished
* game_completed
* punishment_assigned

## game_rounds

Fields may include:

* id
* game_id
* round_number
* starting_player_id
* winner_player_id
* started_at
* completed_at
* result JSON
* timestamps

Create indexes and constraints for:

* Public game identifier
* Game code
* Invitation token
* Game status
* Game and seat number
* Game and nickname
* Game and turn number
* Game and state version
* Card location
* Player card ownership

Use foreign keys where appropriate.

---

# 17. Application Architecture

Keep gameplay logic outside controllers.

Create dedicated classes or services such as:

* GameCreationService
* GameJoiningService
* GameStartService
* DeckService
* CardDealingService
* RuleEngine
* MoveValidationService
* TurnManager
* PileManager
* SpecialCardEffectManager
* WinningConditionService
* PunishmentResolver
* GameStatePresenter
* ReconnectionService

The `RuleEngine` should receive the game’s rule configuration and determine:

* Legal cards
* Whether a player can follow suit
* What happens when they cannot follow suit
* Whether the pile ends
* Who leads next
* Whether special-card effects apply
* Whether the game is complete
* How standings are calculated

Use enums for stable internal values such as:

* Game status
* Card suit
* Card rank
* Card location
* Game action type
* Unable-to-follow action
* Winning condition
* Starting-player method
* Turn direction
* Timeout action

Do not scatter string values throughout the application.

---

# 18. Real-Time Events

Create broadcast events for important game changes.

Possible events:

* PlayerJoined
* PlayerLeft
* PlayerReadyStatusChanged
* GameSettingsUpdated
* GameStarted
* CardPlayed
* PileCollected
* TurnChanged
* PlayerDisconnected
* PlayerReconnected
* PlayerFinished
* GameCompleted
* GameCancelled

Separate public state from private player state.

Public broadcasts may include:

* Current player
* Active suit
* Active pile
* Opponent card counts
* Player scores
* Game status
* Activity messages

Private player channels may include:

* Player’s full hand
* Legal cards
* Private warnings
* Reconnection state

Authorize all private channels securely.

---

# 19. Security Requirements

Implement the following protections:

* Validate all actions on the server.
* Never expose another player’s cards.
* Hash player session tokens in the database.
* Use signed or random invitation tokens.
* Rate-limit game creation and joining.
* Sanitize nicknames and punishment text.
* Prevent duplicate move submissions.
* Use transactions for turn updates.
* Use row locking when processing moves.
* Prevent users from joining completed or cancelled games.
* Prevent the host from changing rules after the game starts.
* Prevent players from impersonating another player.
* Prevent clients from manually changing their hand.
* Do not reveal the original deck order.
* Escape all user-generated content.

Include expiry and cleanup handling for abandoned games.

---

# 20. User Interface Pages

Create the following pages:

1. Landing page
2. Create game page
3. Join game page
4. Game lobby
5. Game table
6. Game results
7. Invalid or expired invitation page
8. Reconnection page
9. Rules explanation modal or page

Use a clean, playful card-game design.

Recommended visual direction:

* Green or dark felt-inspired game table
* Clear red and black card suits
* Rounded player panels
* Large touch-friendly controls
* Smooth card movement animations
* Clear current-turn highlighting
* Minimal clutter on mobile
* Bottom-sheet card hand on mobile
* Desktop card-table layout on larger screens

Include accessible labels and do not rely only on color to communicate card legality or player status.

---

# 21. Activity Messages

Display understandable activity messages such as:

* “Ahmed played the 7 of Hearts.”
* “Mahu could not follow Hearts and collected 5 cards.”
* “Shan changed the active suit to Spades.”
* “The turn direction has been reversed.”
* “Ali has one card remaining.”
* “Mahu finished in second place.”
* “Ahmed collected the most piles and receives the punishment.”

Do not expose hidden card information through activity messages.

---

# 22. Sound and Animation

Add optional sound effects for:

* Player joins
* Card played
* Pile collected
* Player’s turn
* Warning timer
* Game won

Allow each player to mute sounds.

Use animations for:

* Dealing cards
* Playing a card
* Collecting a pile
* Changing turns
* Showing the winner
* Revealing the punishment

Animations must not delay server state updates or prevent user interaction.

---

# 23. Testing

Write automated tests for the game engine.

Include unit and feature tests for:

* Game creation
* Player joining
* Duplicate nickname prevention
* Maximum player limit
* Game start requirements
* Card dealing
* Starting-player selection
* Valid follow-suit move
* Invalid off-suit move
* Player unable to follow suit
* Pile collection
* Collected-card totals
* Collected-pile totals
* Turn rotation
* Reverse direction
* Special-card effects
* Player finishing
* Winning-condition calculation
* Tie resolution
* Punishment assignment
* Duplicate action prevention
* Simultaneous move protection
* Reconnection
* Game cancellation
* Invitation expiry
* Unauthorized private-hand access

Create deterministic tests by injecting a predictable deck order into the game engine.

Do not depend on random shuffling inside automated tests.

---

# 24. Development Phases

Implement the project in phases.

## Phase 1: Project Foundation

* Database schema
* Models
* Enums
* Game creation
* Invitation links
* Join flow
* Lobby
* Basic rule settings

## Phase 2: Core Game Engine

* Deck creation
* Shuffling
* Dealing
* Turn order
* Following suit
* Pile collection
* Hand management
* Winning condition

## Phase 3: Real-Time Multiplayer

* Broadcasting
* Private channels
* Live lobby
* Live turns
* Connection state
* Reconnection

## Phase 4: Configurable Rules

* Rule presets
* Alternative pile behavior
* Starting-player settings
* Special cards
* Turn timer
* Tie-breaking rules

## Phase 5: Punishments and Results

* Punishment setup
* Player acknowledgement
* Final standings
* Punishment resolution
* Rematch support

## Phase 6: Polish and Testing

* Responsive interface
* Animations
* Sounds
* Security review
* Automated tests
* Error handling
* Abandoned-game cleanup

Complete each phase before beginning the next.

Do not generate placeholder-only code. Implement working migrations, models, services, controllers, requests, policies, events, Vue pages, components, routes, and tests.

---

# 25. Important Development Rules

Follow these implementation rules:

1. Do not place game logic inside Vue components.
2. Do not place complex game logic inside controllers.
3. The server is the authoritative source of game state.
4. Never trust card legality calculated by the frontend.
5. Never expose opponents’ hidden cards.
6. Make all rule behavior driven by the saved game configuration.
7. Use database transactions for moves.
8. Use state-version checks to prevent stale actions.
9. Keep real-time payloads small.
10. Make reconnection part of the initial architecture.
11. Write tests before introducing advanced rule variants.
12. Do not run destructive database commands such as `migrate:fresh` unless explicitly instructed.
13. Before changing an existing file, inspect the current implementation.
14. Preserve existing project conventions.
15. Document any assumptions in the project documentation.

---

# 26. Required Deliverables

Produce:

* Database migrations
* Eloquent models and relationships
* PHP enums
* Form request validation
* Controllers
* Policies and authorization
* Game-domain services
* Rule engine
* Broadcasting events and channels
* Queue jobs where necessary
* Vue pages
* Reusable Vue components
* Tailwind styling
* Routes
* Automated tests
* Seeders for rule presets
* Game cleanup command
* README documentation
* Architecture documentation
* Game-rule documentation

The README must explain:

* Installation
* Environment configuration
* WebSocket setup
* Queue setup
* Database setup
* Running tests
* Starting the development servers
* Creating a game
* Joining a game
* Adding a new rule
* Adding a new special-card effect
* Deployment considerations

Start by preparing:

1. The proposed database schema
2. The game-state model
3. The rule configuration structure
4. The backend service architecture
5. The WebSocket event flow
6. The implementation phases

After presenting those items, proceed with Phase 1.