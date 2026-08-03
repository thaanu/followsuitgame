<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import ActivityFeed from '@/components/game/ActivityFeed.vue';
import PlayerAvatar from '@/components/game/PlayerAvatar.vue';
import RulesSummary from '@/components/game/RulesSummary.vue';
import AlertBanner from '@/components/ui/AlertBanner.vue';
import AppButton from '@/components/ui/AppButton.vue';
import AppPanel from '@/components/ui/AppPanel.vue';
import { useGameChannel } from '@/composables/useGameChannel';
import { usePlayerToken, withPlayerToken } from '@/composables/usePlayerToken';
import { useSound } from '@/composables/useSound';
import AppLayout from '@/layouts/AppLayout.vue';
import type {
    GameResults,
    PublicGameState,
    ReverbConfig,
    RuleSummaryRow,
} from '@/types/game';

const props = defineProps<{
    state: PublicGameState;
    results: GameResults;
    cancelled: boolean;
    me: { id: string; nickname: string; is_host: boolean } | null;
    rulesSummary: RuleSummaryRow[];
    rematch: { code: string; url: string } | null;
}>();

const page = usePage();
const reverb = computed(() => page.props.reverb as ReverbConfig);
const { token } = usePlayerToken(props.state.id, null);
const { play } = useSound();

const copied = ref(false);
const revealPunishment = ref(false);

useGameChannel({
    gameId: props.state.id,
    playerId: props.me?.id ?? '',
    token: token.value,
    reverb: reverb.value,
    onPublic: () =>
        router.reload({
            headers: withPlayerToken(token.value),
            only: ['rematch', 'results', 'state'],
        }),
});

onMounted(() => {
    if (!props.cancelled) {
        play('win');
    }

    // Give the standings a beat before naming the punished player.
    setTimeout(() => (revealPunishment.value = true), 900);
});

const duration = computed(() => {
    const seconds = props.results.duration_seconds;
    const minutes = Math.floor(seconds / 60);

    return minutes > 0 ? `${minutes} min ${seconds % 60}s` : `${seconds}s`;
});

const asText = computed(() => {
    const lines = [
        `Follow Suit — ${props.state.name || props.state.code}`,
        ...props.results.standings.map(
            (row) =>
                `${row.position}. ${row.nickname} — ${row.cards_remaining} left, ${row.cards_collected} collected, ${row.piles_collected} piles`,
        ),
    ];

    if (props.results.punishment?.player) {
        lines.push(
            `Punishment (${props.results.punishment.title}): ${props.results.punishment.player.nickname}`,
        );
    }

    return lines.join('\n');
});

const copyResults = async () => {
    try {
        await navigator.clipboard.writeText(asText.value);
        copied.value = true;
        setTimeout(() => (copied.value = false), 2000);
    } catch {
        // Clipboard is blocked; the table is on screen.
    }
};

const startRematch = () =>
    router.post(
        `/games/${props.state.id}/rematch`,
        {},
        { headers: withPlayerToken(token.value) },
    );
</script>

<template>
    <Head title="Results" />

    <AppLayout back="/" backLabel="Home">
        <div class="text-center">
            <template v-if="cancelled">
                <p class="text-5xl" aria-hidden="true">🚫</p>
                <h1 class="mt-3 text-2xl font-bold text-white sm:text-3xl">
                    Game cancelled
                </h1>
                <p class="mt-1 text-felt-100/80">
                    The host closed this table before it finished.
                </p>
            </template>

            <template v-else>
                <p class="text-5xl" aria-hidden="true">🏆</p>
                <h1 class="mt-3 text-2xl font-bold text-white sm:text-3xl">
                    <span v-if="results.shared_win">It's a shared win!</span>
                    <span v-else-if="results.winner"
                        >{{ results.winner.nickname }} wins!</span
                    >
                    <span v-else>Game over</span>
                </h1>
                <p class="mt-1 text-felt-100/80">
                    {{ results.turns_played }} turns ·
                    {{ results.rounds_played }} round{{
                        results.rounds_played === 1 ? '' : 's'
                    }}
                    ·
                    {{ duration }}
                </p>
            </template>
        </div>

        <div class="mt-6 grid gap-4 lg:grid-cols-[1fr_20rem]">
            <div class="space-y-4">
                <AppPanel title="Final standings">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[34rem] text-sm">
                            <thead
                                class="text-left text-xs tracking-wide text-felt-200/60 uppercase"
                            >
                                <tr>
                                    <th scope="col" class="pr-2 pb-2">#</th>
                                    <th scope="col" class="pr-2 pb-2">
                                        Player
                                    </th>
                                    <th
                                        scope="col"
                                        class="pr-2 pb-2 text-right"
                                    >
                                        In hand
                                    </th>
                                    <th
                                        scope="col"
                                        class="pr-2 pb-2 text-right"
                                    >
                                        Collected
                                    </th>
                                    <th
                                        scope="col"
                                        class="pr-2 pb-2 text-right"
                                    >
                                        Piles
                                    </th>
                                    <th scope="col" class="pb-2 text-right">
                                        Missed suit
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="row in results.standings"
                                    :key="row.player_id"
                                    class="border-t border-white/10"
                                    :class="
                                        row.player_id === me?.id
                                            ? 'bg-brass-400/10'
                                            : ''
                                    "
                                >
                                    <td
                                        class="py-3 pr-2 font-bold text-brass-300"
                                    >
                                        {{ row.position }}
                                    </td>
                                    <td class="py-3 pr-2">
                                        <span class="flex items-center gap-2">
                                            <PlayerAvatar
                                                :initials="row.initials"
                                                :nickname="row.nickname"
                                                size="sm"
                                            />
                                            <span
                                                class="font-medium text-white"
                                                >{{ row.nickname }}</span
                                            >
                                            <span
                                                v-if="row.tied"
                                                class="text-xs text-felt-200/70"
                                                >(tied)</span
                                            >
                                        </span>
                                    </td>
                                    <td
                                        class="py-3 pr-2 text-right tabular-nums"
                                    >
                                        {{ row.cards_remaining }}
                                    </td>
                                    <td
                                        class="py-3 pr-2 text-right tabular-nums"
                                    >
                                        {{ row.cards_collected }}
                                    </td>
                                    <td
                                        class="py-3 pr-2 text-right tabular-nums"
                                    >
                                        {{ row.piles_collected }}
                                    </td>
                                    <td class="py-3 text-right tabular-nums">
                                        {{ row.failed_follow_suit }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </AppPanel>

                <Transition name="reveal">
                    <div
                        v-if="results.punishment && revealPunishment"
                        class="rounded-2xl border border-brass-400/50 bg-brass-400/15 p-5"
                    >
                        <p
                            class="text-xs tracking-[0.2em] text-brass-300 uppercase"
                        >
                            The punishment
                        </p>

                        <div class="mt-2 flex items-start gap-3">
                            <span
                                class="text-4xl leading-none"
                                aria-hidden="true"
                            >
                                {{ results.punishment.emoji || '🎯' }}
                            </span>
                            <div>
                                <h2 class="text-xl font-bold text-white">
                                    <span v-if="results.punishment.player">
                                        {{ results.punishment.player.nickname }}
                                        {{ results.punishment.reason }}
                                    </span>
                                    <span v-else
                                        >Nobody has been chosen yet</span
                                    >
                                </h2>
                                <p class="mt-1 font-semibold text-brass-300">
                                    {{ results.punishment.title }}
                                </p>
                                <p
                                    v-if="results.punishment.description"
                                    class="mt-1 text-sm text-felt-100/90"
                                >
                                    {{ results.punishment.description }}
                                </p>
                            </div>
                        </div>

                        <AlertBanner
                            v-if="results.punishment.tied"
                            tone="warning"
                            class="mt-3"
                        >
                            <span
                                v-if="results.punishment.pending_host_decision"
                            >
                                {{
                                    results.punishment.tied_players.join(
                                        ' and ',
                                    )
                                }}
                                tied, and the rules leave the final call to the
                                host.
                            </span>
                            <span v-else>
                                {{
                                    results.punishment.tied_players.join(
                                        ' and ',
                                    )
                                }}
                                tied — the tie-break rule settled it.
                            </span>
                        </AlertBanner>
                    </div>
                </Transition>

                <AppPanel title="Rules that were in play">
                    <RulesSummary :rows="rulesSummary" columns />
                </AppPanel>
            </div>

            <aside class="space-y-4">
                <AppPanel title="What next?">
                    <div class="space-y-2">
                        <AlertBanner v-if="rematch" tone="success">
                            <p class="font-semibold">A rematch is waiting.</p>
                            <AppButton
                                size="sm"
                                class="mt-2"
                                :href="`/games/join/${rematch.code}`"
                            >
                                Join the rematch
                            </AppButton>
                        </AlertBanner>

                        <AppButton
                            v-if="me?.is_host && !rematch"
                            block
                            @click="startRematch"
                        >
                            Play again with the same rules
                        </AppButton>

                        <AppButton
                            v-if="me?.is_host"
                            href="/games/create"
                            variant="secondary"
                            block
                        >
                            Change the rules and start fresh
                        </AppButton>

                        <AppButton
                            variant="secondary"
                            block
                            @click="copyResults"
                        >
                            {{ copied ? 'Copied!' : 'Copy results' }}
                        </AppButton>

                        <AppButton href="/" variant="ghost" block
                            >Leave the game</AppButton
                        >
                    </div>
                </AppPanel>

                <AppPanel title="How it played out">
                    <ActivityFeed :entries="state.activity" />
                </AppPanel>
            </aside>
        </div>
    </AppLayout>
</template>

<style scoped>
.reveal-enter-active {
    transition: all 0.5s cubic-bezier(0.22, 1, 0.36, 1);
}

.reveal-enter-from {
    opacity: 0;
    transform: translateY(16px) scale(0.97);
}
</style>
