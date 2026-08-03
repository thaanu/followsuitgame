<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import ActivityFeed from '@/components/game/ActivityFeed.vue';
import CardBack from '@/components/game/CardBack.vue';
import PlayerPanel from '@/components/game/PlayerPanel.vue';
import PlayingCard from '@/components/game/PlayingCard.vue';
import PunishmentCard from '@/components/game/PunishmentCard.vue';
import RulesSummary from '@/components/game/RulesSummary.vue';
import SuitPicker from '@/components/game/SuitPicker.vue';
import TurnTimer from '@/components/game/TurnTimer.vue';
import AlertBanner from '@/components/ui/AlertBanner.vue';
import AppButton from '@/components/ui/AppButton.vue';
import { useCountdown } from '@/composables/useCountdown';
import { useGameChannel } from '@/composables/useGameChannel';
import { useHeartbeat } from '@/composables/useHeartbeat';
import {
    idempotencyKey,
    usePlayerToken,
    withPlayerToken,
} from '@/composables/usePlayerToken';
import { useSound } from '@/composables/useSound';
import type {
    Card,
    CardSuit,
    PrivateGameState,
    PublicGameState,
    PunishmentSummary,
    ReverbConfig,
    RuleSummaryRow,
} from '@/types/game';

const props = defineProps<{
    state: PublicGameState;
    hand: PrivateGameState;
    me: { id: string; nickname: string; is_host: boolean; seat_number: number };
    playerToken: string | null;
    rulesSummary: RuleSummaryRow[];
    punishment: PunishmentSummary | null;
    heartbeatSeconds: number;
}>();

const page = usePage();
const reverb = computed(() => page.props.reverb as ReverbConfig);

const { token } = usePlayerToken(props.state.id, props.playerToken);
const { play, muted, toggleMute } = useSound();

const table = ref<PublicGameState>(props.state);
const mine = ref<PrivateGameState>(props.hand);

const selectedCardId = ref<number | null>(null);
const suitPickerCard = ref<Card | null>(null);
const submitting = ref(false);
const showSheet = ref<'rules' | 'activity' | null>(null);

const opponents = computed(() =>
    table.value.players.filter((player) => player.id !== props.me.id),
);
const currentPlayer = computed(
    () => table.value.players.find((player) => player.is_current) ?? null,
);
const isMyTurn = computed(
    () => mine.value.is_my_turn && !table.value.is_paused,
);
const topOfPile = computed(
    () => table.value.pile[table.value.pile.length - 1] ?? null,
);
const selectedCard = computed(
    () =>
        mine.value.hand.find((card) => card.id === selectedCardId.value) ??
        null,
);

const deadline = computed(() => table.value.turn_deadline_at);
const serverTime = computed(() => table.value.server_time);
const { secondsRemaining } = useCountdown(deadline, serverTime);

/* Group the hand by suit so a big hand is still readable on a phone. */
const SUIT_ORDER: CardSuit[] = [
    'spades',
    'hearts',
    'clubs',
    'diamonds',
    'none',
];

const groupedHand = computed(() =>
    SUIT_ORDER.map((suit) => ({
        suit,
        cards: mine.value.hand
            .filter((card) => card.suit === suit)
            .sort((a, b) => a.rank_value - b.rank_value),
    })).filter((group) => group.cards.length > 0),
);

const { connected } = useGameChannel({
    gameId: props.state.id,
    playerId: props.me.id,
    token: token.value ?? props.playerToken,
    reverb: reverb.value,
    onPublic: (event, payload) => {
        table.value = payload.state;

        if (event === 'card.played') {
            play('play');
        }

        if (event === 'pile.collected') {
            play('collect');
        }

        if (event === 'game.completed' || event === 'game.cancelled') {
            play('win');
            router.visit(`/games/${props.state.id}/results`, {
                headers: withPlayerToken(token.value),
            });
        }
    },
    onPrivate: (payload) => {
        mine.value = payload.state;
        selectedCardId.value = null;
        submitting.value = false;
    },
});

useHeartbeat({
    url: `/games/${props.state.id}/heartbeat`,
    token: token.value ?? props.playerToken,
    seconds: props.heartbeatSeconds,
    currentVersion: () => table.value.state_version,
    onBehind: () =>
        router.reload({
            headers: withPlayerToken(token.value),
            only: ['state', 'hand'],
        }),
});

/* Announce the player's own turn, and warn them when the clock runs low. */
watch(isMyTurn, (mineNow) => mineNow && play('turn'));

watch(secondsRemaining, (value, previous) => {
    if (isMyTurn.value && value === 5 && previous !== 5) {
        play('warning');
    }
});

const selectCard = (card: Card) => {
    if (!isMyTurn.value || !mine.value.legal_card_ids.includes(card.id)) {
        return;
    }

    selectedCardId.value = selectedCardId.value === card.id ? null : card.id;
};

const confirmPlay = () => {
    const card = selectedCard.value;

    if (!card || submitting.value) {
        return;
    }

    if (mine.value.requires_suit_choice_for.includes(card.id)) {
        suitPickerCard.value = card;

        return;
    }

    submitCard(card, null);
};

const submitCard = (card: Card, chosenSuit: CardSuit | null) => {
    submitting.value = true;
    suitPickerCard.value = null;

    router.post(
        `/games/${props.state.id}/play`,
        {
            card_id: card.id,
            chosen_suit: chosenSuit,
            state_version: table.value.state_version,
            idempotency_key: idempotencyKey('play'),
        },
        {
            preserveScroll: true,
            headers: withPlayerToken(token.value),
            onFinish: () => (submitting.value = false),
        },
    );
};

const resolveTurn = () => {
    if (submitting.value) {
        return;
    }

    submitting.value = true;

    router.post(
        `/games/${props.state.id}/resolve-turn`,
        {
            state_version: table.value.state_version,
            idempotency_key: idempotencyKey('resolve'),
        },
        {
            preserveScroll: true,
            headers: withPlayerToken(token.value),
            onFinish: () => (submitting.value = false),
        },
    );
};

const gameError = computed(
    () =>
        (page.props.flash as { gameError?: { message: string } })?.gameError ??
        null,
);
</script>

<template>
    <Head :title="`Playing — ${table.name ?? table.code}`" />

    <div class="felt-surface flex min-h-dvh flex-col">
        <!-- Status bar -->
        <header class="border-b border-white/10 px-3 py-2">
            <div
                class="mx-auto flex max-w-5xl items-center justify-between gap-2"
            >
                <div class="flex min-w-0 items-center gap-2">
                    <span class="text-lg" aria-hidden="true">🂡</span>
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold text-white">
                            {{ table.name || 'Follow Suit' }}
                        </p>
                        <p class="truncate text-xs text-felt-200/70">
                            Round {{ table.round_number }} · Turn
                            {{ table.turn_number }} ·
                            {{ table.direction_label }}
                        </p>
                    </div>
                </div>

                <div class="flex shrink-0 items-center gap-2">
                    <TurnTimer
                        :seconds-remaining="secondsRemaining"
                        :total-seconds="table.turn_timer_seconds"
                    />
                    <span
                        :class="[
                            'rounded-full px-2 py-1 text-[10px] font-bold uppercase',
                            connected
                                ? 'bg-felt-500/25 text-felt-100'
                                : 'bg-white/10 text-felt-200/70',
                        ]"
                    >
                        {{ connected ? 'Live' : '···' }}
                    </span>
                    <button
                        type="button"
                        class="rounded-lg bg-white/10 px-2 py-1.5 text-sm"
                        :aria-label="muted ? 'Unmute sounds' : 'Mute sounds'"
                        :aria-pressed="muted"
                        @click="toggleMute"
                    >
                        {{ muted ? '🔇' : '🔊' }}
                    </button>
                </div>
            </div>
        </header>

        <AlertBanner v-if="table.is_paused" tone="warning" class="mx-3 mt-3">
            The game is paused while a player reconnects.
        </AlertBanner>

        <AlertBanner v-if="gameError" tone="danger" class="mx-3 mt-3">
            {{ gameError.message }}
        </AlertBanner>

        <main
            class="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-4 px-3 py-4"
        >
            <!-- Opponents -->
            <section
                aria-label="Other players"
                class="grid gap-2 sm:grid-cols-3"
            >
                <PlayerPanel
                    v-for="player in opponents"
                    :key="player.id"
                    :player="player"
                    compact
                />
            </section>

            <!-- The table -->
            <section
                class="panel relative flex flex-1 flex-col items-center justify-center gap-4 p-4 sm:p-8"
            >
                <div
                    class="flex flex-wrap items-center justify-center gap-3 text-center"
                >
                    <div
                        v-if="table.active_suit"
                        class="rounded-xl bg-black/25 px-3 py-2"
                    >
                        <p
                            class="text-[10px] tracking-wide text-felt-200/70 uppercase"
                        >
                            Active suit
                        </p>
                        <p
                            :class="[
                                'text-lg font-bold',
                                table.active_suit === 'hearts' ||
                                table.active_suit === 'diamonds'
                                    ? 'text-red-400'
                                    : 'text-white',
                            ]"
                        >
                            <span aria-hidden="true">{{
                                table.active_suit_symbol
                            }}</span>
                            {{ table.active_suit_label }}
                        </p>
                    </div>

                    <div class="rounded-xl bg-black/25 px-3 py-2">
                        <p
                            class="text-[10px] tracking-wide text-felt-200/70 uppercase"
                        >
                            Pile
                        </p>
                        <p class="text-lg font-bold text-white">
                            {{ table.pile_count }}
                        </p>
                    </div>

                    <div
                        v-if="table.deck_count > 0"
                        class="rounded-xl bg-black/25 px-3 py-2"
                    >
                        <p
                            class="text-[10px] tracking-wide text-felt-200/70 uppercase"
                        >
                            Deck
                        </p>
                        <p class="text-lg font-bold text-white">
                            {{ table.deck_count }}
                        </p>
                    </div>
                </div>

                <!-- Only the top few pile cards are drawn; the count above carries the rest. -->
                <div class="flex min-h-28 items-center justify-center">
                    <TransitionGroup
                        name="pile"
                        tag="div"
                        class="flex items-center"
                    >
                        <PlayingCard
                            v-for="(card, index) in table.pile.slice(-4)"
                            :key="card.id"
                            :card="card"
                            size="md"
                            :class="index > 0 ? '-ml-8 sm:-ml-6' : ''"
                            :style="{
                                zIndex: index,
                                transform: `rotate(${(index - 1.5) * 4}deg)`,
                            }"
                            :dealt="index === table.pile.slice(-4).length - 1"
                        />
                    </TransitionGroup>

                    <p v-if="!topOfPile" class="text-sm text-felt-200/60">
                        The pile is empty —
                        {{ currentPlayer?.nickname ?? 'somebody' }} leads.
                    </p>
                </div>

                <p class="text-center text-sm">
                    <span v-if="isMyTurn" class="font-bold text-brass-300"
                        >Your turn</span
                    >
                    <span v-else-if="currentPlayer" class="text-felt-100/80">
                        Waiting for
                        <strong class="text-white">{{
                            currentPlayer.nickname
                        }}</strong>
                    </span>
                </p>

                <div class="absolute top-3 right-3 flex gap-1.5">
                    <button
                        type="button"
                        class="rounded-lg bg-white/10 px-2.5 py-1.5 text-xs font-medium text-white hover:bg-white/20"
                        @click="
                            showSheet = showSheet === 'rules' ? null : 'rules'
                        "
                    >
                        Rules
                    </button>
                    <button
                        type="button"
                        class="rounded-lg bg-white/10 px-2.5 py-1.5 text-xs font-medium text-white hover:bg-white/20"
                        @click="
                            showSheet =
                                showSheet === 'activity' ? null : 'activity'
                        "
                    >
                        Activity
                    </button>
                </div>
            </section>

            <!-- Desktop activity feed; on mobile it lives in the sheet. -->
            <section class="panel hidden p-4 lg:block">
                <h2 class="mb-2 text-sm font-semibold text-white">
                    Recent activity
                </h2>
                <ActivityFeed :entries="table.activity" />
            </section>
        </main>

        <!-- The player's hand, docked to the bottom on every screen size -->
        <div
            class="sticky bottom-0 border-t border-white/10 bg-felt-950/95 backdrop-blur"
        >
            <div class="mx-auto max-w-5xl px-3 py-3">
                <div class="mb-2 flex items-center justify-between gap-2">
                    <p
                        class="text-xs font-semibold tracking-wide text-felt-200/70 uppercase"
                    >
                        Your hand · {{ mine.hand.length }} card{{
                            mine.hand.length === 1 ? '' : 's'
                        }}
                    </p>
                    <p
                        v-if="isMyTurn && mine.legal_card_ids.length"
                        class="text-xs text-felt-200/70"
                    >
                        {{ mine.legal_card_ids.length }} playable
                    </p>
                </div>

                <div class="-mx-1 flex gap-3 overflow-x-auto px-1 pt-5 pb-2">
                    <div
                        v-for="group in groupedHand"
                        :key="group.suit"
                        class="flex shrink-0 gap-1.5"
                    >
                        <PlayingCard
                            v-for="card in group.cards"
                            :key="card.id"
                            :card="card"
                            selectable
                            :selected="selectedCardId === card.id"
                            :disabled="
                                !isMyTurn ||
                                !mine.legal_card_ids.includes(card.id)
                            "
                            @click="selectCard(card)"
                        />
                    </div>

                    <div
                        v-if="!mine.hand.length"
                        class="flex items-center gap-2 py-6 text-sm text-felt-200/70"
                    >
                        <CardBack size="sm" />
                        <span>Your hand is empty. Well played!</span>
                    </div>
                </div>

                <div class="mt-1 grid gap-2 sm:grid-cols-2">
                    <AppButton
                        v-if="isMyTurn && !mine.must_collect"
                        size="lg"
                        block
                        :disabled="!selectedCard || submitting"
                        @click="confirmPlay"
                    >
                        {{
                            selectedCard
                                ? `Play the ${selectedCard.label}`
                                : 'Select a card to play'
                        }}
                    </AppButton>

                    <AppButton
                        v-if="
                            isMyTurn &&
                            (mine.must_collect || mine.may_voluntarily_collect)
                        "
                        size="lg"
                        block
                        :variant="mine.must_collect ? 'primary' : 'secondary'"
                        :disabled="submitting"
                        @click="resolveTurn"
                    >
                        {{
                            mine.must_collect
                                ? `Take the pile (${table.pile_count})`
                                : 'Collect the pile instead'
                        }}
                    </AppButton>

                    <p
                        v-if="!isMyTurn"
                        class="col-span-full py-3 text-center text-sm text-felt-200/70"
                        aria-live="polite"
                    >
                        Not your turn yet.
                    </p>
                </div>

                <p
                    v-if="isMyTurn && mine.must_collect"
                    class="mt-1 text-center text-xs text-brass-300"
                >
                    You have nothing in {{ table.active_suit_label }}, so the
                    pile comes to you.
                </p>
            </div>
        </div>

        <!-- Bottom sheet for rules and activity on small screens -->
        <div
            v-if="showSheet"
            class="fixed inset-0 z-40 flex items-end bg-black/60"
            role="dialog"
            aria-modal="true"
            @click.self="showSheet = null"
        >
            <div
                class="max-h-[75vh] w-full overflow-y-auto rounded-t-3xl border-t border-white/10 bg-felt-900 p-5"
            >
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-white">
                        {{
                            showSheet === 'rules'
                                ? 'Rules for this table'
                                : 'Recent activity'
                        }}
                    </h2>
                    <button
                        type="button"
                        class="rounded-lg bg-white/10 px-3 py-1.5 text-sm"
                        @click="showSheet = null"
                    >
                        Close
                    </button>
                </div>

                <template v-if="showSheet === 'rules'">
                    <RulesSummary :rows="rulesSummary" />
                    <PunishmentCard
                        v-if="punishment"
                        :punishment="punishment"
                        class="mt-4"
                    />
                    <Link
                        :href="`/games/${table.id}/results`"
                        class="mt-4 inline-block text-sm text-felt-200/70 underline"
                    >
                        View standings so far
                    </Link>
                </template>

                <ActivityFeed v-else :entries="table.activity" />
            </div>
        </div>

        <SuitPicker
            v-if="suitPickerCard"
            :card-label="suitPickerCard.label"
            @choose="(suit) => submitCard(suitPickerCard!, suit)"
            @cancel="suitPickerCard = null"
        />
    </div>
</template>

<style scoped>
.pile-enter-active {
    animation: card-drop 0.28s cubic-bezier(0.22, 1, 0.36, 1);
}

.pile-leave-active {
    animation: pile-sweep 0.3s ease-in forwards;
    position: absolute;
}
</style>
