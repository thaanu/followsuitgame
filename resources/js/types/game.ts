export type CardSuit = 'hearts' | 'diamonds' | 'clubs' | 'spades' | 'none';

export type GameStatusValue =
    'lobby' | 'in_progress' | 'completed' | 'cancelled';

export interface Card {
    id: number;
    code: string;
    suit: CardSuit;
    rank: string;
    rank_value: number;
    label: string;
    is_red: boolean;
    played_by?: string | null;
    is_legal?: boolean;
}

export interface PlayerState {
    id: string;
    nickname: string;
    initials: string;
    seat_number: number;
    is_host: boolean;
    is_ready: boolean;
    has_acknowledged_punishment: boolean;
    connection_status: 'connected' | 'disconnected';
    is_connected: boolean;
    hand_count: number;
    cards_collected: number;
    piles_collected: number;
    failed_follow_suit: number;
    finishing_position: number | null;
    is_current: boolean;
}

export interface ActivityEntry {
    id: number;
    type: string;
    message: string;
    at: string | null;
}

export interface RuleSummaryRow {
    label: string;
    value: string;
}

export interface PunishmentSummary {
    title: string;
    description: string;
    emoji: string | null;
    severity: string | null;
    severity_label: string | null;
    target: string;
    target_label: string;
}

export interface PublicGameState {
    id: string;
    code: string;
    name: string | null;
    status: GameStatusValue;
    status_label: string;
    state_version: number;
    round_number: number;
    turn_number: number;
    direction: 'clockwise' | 'counterclockwise';
    direction_label: string;
    active_suit: CardSuit | null;
    active_suit_label: string | null;
    active_suit_symbol: string | null;
    current_player_id: string | null;
    host_player_id: string | null;
    maximum_players: number;
    is_paused: boolean;
    turn_deadline_at: string | null;
    turn_timer_seconds: number | null;
    server_time: string;
    started_at: string | null;
    completed_at: string | null;
    pile: Card[];
    pile_count: number;
    deck_count: number;
    players: PlayerState[];
    activity: ActivityEntry[];
}

export interface LobbyState extends PublicGameState {
    rule_preset: string;
    rule_preset_label: string;
    rules_summary: RuleSummaryRow[];
    rules: RuleSet;
    punishment: PunishmentSummary | null;
    punishment_enabled: boolean;
    can_start: boolean;
    start_blockers: string[];
    invitation_url?: string;
}

export interface PrivateGameState {
    player_id: string;
    state_version: number;
    hand: Card[];
    legal_card_ids: number[];
    is_my_turn: boolean;
    must_collect: boolean;
    may_voluntarily_collect: boolean;
    requires_suit_choice_for: number[];
}

export interface RuleSet {
    general: {
        deck_type: string;
        deal_method: string;
        fixed_cards_per_player: number;
        starting_player_method: string;
        starting_card_code: string;
        turn_direction: string;
    };
    follow_suit: {
        unable_to_follow_action: string;
        draw_card_count: number;
        allow_voluntary_collect: boolean;
        next_lead_rule: string;
    };
    pile: {
        end_condition: string;
        fixed_card_count: number;
        collected_cards_destination: string;
    };
    ranking: {
        ace_rule: string;
        round_winner: string;
    };
    special_cards: {
        enabled: boolean;
        effects: Record<string, boolean>;
        clear_pile_rank: string | null;
    };
    winning: {
        condition: string;
        round_limit: number;
        tie_break: string;
    };
    timer: {
        enabled: boolean;
        seconds: number;
        timeout_action: string;
    };
    connection: {
        grace_period_seconds: number;
        disconnect_behaviour: string;
    };
}

export interface Option {
    value: string;
    label: string;
}

export interface RuleOptions {
    deck_types: Option[];
    deal_methods: Option[];
    starting_player_methods: Option[];
    turn_directions: Option[];
    unable_to_follow_actions: Option[];
    next_lead_rules: Option[];
    pile_end_conditions: Option[];
    collected_cards_destinations: Option[];
    ace_rules: Option[];
    round_winners: Option[];
    winning_conditions: Option[];
    tie_break_rules: Option[];
    timeout_actions: Option[];
    disconnect_behaviours: Option[];
    punishment_targets: Option[];
    punishment_severities: Option[];
    special_card_effects: Option[];
    clear_pile_ranks: Option[];
    cards: { code: string; label: string }[];
    timer_presets: number[];
}

export interface Preset {
    value: string;
    label: string;
    description: string;
    rules: RuleSet;
}

export interface StandingRow {
    player_id: string;
    nickname: string;
    initials: string;
    is_host: boolean;
    position: number;
    cards_remaining: number;
    cards_collected: number;
    piles_collected: number;
    failed_follow_suit: number;
    tied: boolean;
}

export interface GameResults {
    winner: StandingRow | null;
    shared_win: boolean;
    standings: StandingRow[];
    turns_played: number;
    rounds_played: number;
    duration_seconds: number;
    punishment:
        | (PunishmentSummary & {
              player: {
                  player_id: string;
                  nickname: string;
                  initials: string;
              } | null;
              reason: string;
              tied: boolean;
              tied_players: string[];
              pending_host_decision: boolean;
          })
        | null;
}

export interface ReverbConfig {
    key: string;
    host: string;
    port: number;
    scheme: string;
}

export interface PunishmentDraft {
    title: string;
    description: string;
    emoji: string;
    severity: string;
    target: string;
    agreement_confirmed: boolean;
}
