<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import InviteBox from '@/components/game/InviteBox.vue';
import PlayerAvatar from '@/components/game/PlayerAvatar.vue';
import PunishmentCard from '@/components/game/PunishmentCard.vue';
import PunishmentForm from '@/components/game/PunishmentForm.vue';
import RulesForm from '@/components/game/RulesForm.vue';
import RulesSummary from '@/components/game/RulesSummary.vue';
import AlertBanner from '@/components/ui/AlertBanner.vue';
import AppButton from '@/components/ui/AppButton.vue';
import AppPanel from '@/components/ui/AppPanel.vue';
import FormField from '@/components/ui/FormField.vue';
import { useGameChannel } from '@/composables/useGameChannel';
import { useHeartbeat } from '@/composables/useHeartbeat';
import { usePlayerToken, withPlayerToken } from '@/composables/usePlayerToken';
import { useSound } from '@/composables/useSound';
import AppLayout from '@/layouts/AppLayout.vue';
import { deepClone } from '@/lib/utils';
import type {
    LobbyState,
    Preset,
    PunishmentDraft,
    ReverbConfig,
    RuleOptions,
    RuleSet,
} from '@/types/game';

const props = defineProps<{
    state: LobbyState;
    me: {
        id: string;
        nickname: string;
        initials: string;
        is_host: boolean;
        is_ready: boolean;
        has_acknowledged_punishment: boolean;
        seat_number: number;
    };
    playerToken: string | null;
    options: RuleOptions;
    presets: Preset[];
    punishmentNotice: string;
    heartbeatSeconds: number;
}>();

const page = usePage();
const reverb = computed(() => page.props.reverb as ReverbConfig);

const { token } = usePlayerToken(props.state.id, props.playerToken);
const { play, muted, toggleMute } = useSound();

const live = ref<LobbyState>(props.state);
const editingSettings = ref(false);

const players = computed(() => live.value.players);
const me = computed(
    () => players.value.find((player) => player.id === props.me.id) ?? null,
);
const isHost = computed(() => props.me.is_host);
const seatsLeft = computed(
    () => live.value.maximum_players - players.value.length,
);

const { connected } = useGameChannel({
    gameId: props.state.id,
    playerId: props.me.id,
    token: token.value ?? props.playerToken,
    reverb: reverb.value,
    onPublic: (event, payload) => {
        live.value = payload.state as LobbyState;

        if (event === 'player.joined') {
            play('join');
        }

        // The lobby is over: go wherever the game has moved to.
        if (event === 'game.started') {
            router.visit(`/games/${props.state.id}/table`, {
                headers: withPlayerToken(token.value),
            });
        }

        if (event === 'game.cancelled') {
            router.visit(`/games/${props.state.id}/results`, {
                headers: withPlayerToken(token.value),
            });
        }
    },
});

useHeartbeat({
    url: `/games/${props.state.id}/heartbeat`,
    token: token.value ?? props.playerToken,
    seconds: props.heartbeatSeconds,
    currentVersion: () => live.value.state_version,
    onBehind: () =>
        router.reload({
            headers: withPlayerToken(token.value),
            only: ['state'],
        }),
});

const send = (
    url: string,
    method: 'post' | 'put' | 'delete' = 'post',
    data: Record<string, string | number | boolean> = {},
) =>
    router.visit(url, {
        method,
        data,
        preserveScroll: true,
        headers: withPlayerToken(token.value),
    });

const toggleReady = () =>
    send(`/games/${props.state.id}/ready`, 'post', {
        ready: !me.value?.is_ready,
    });
const acknowledge = () =>
    send(`/games/${props.state.id}/acknowledge-punishment`);
const start = () => send(`/games/${props.state.id}/start`);
const cancel = () => send(`/games/${props.state.id}/cancel`);
const leave = () => send(`/games/${props.state.id}/leave`);
const removePlayer = (playerId: string) =>
    send(`/games/${props.state.id}/players/${playerId}`, 'delete');

/* Host-only settings editing, reusing the same forms as the create screen. */
const settingsForm = useForm({
    name: props.state.name ?? '',
    maximum_players: props.state.maximum_players,
    rule_preset: props.state.rule_preset,
    rules: deepClone(props.state.rules) as RuleSet,
    punishment_enabled: props.state.punishment_enabled,
    punishment: {
        title: props.state.punishment?.title ?? '',
        description: props.state.punishment?.description ?? '',
        emoji: props.state.punishment?.emoji ?? '',
        severity: props.state.punishment?.severity ?? '',
        target: props.state.punishment?.target ?? 'most_cards_collected',
        agreement_confirmed: props.state.punishment_enabled,
    } as PunishmentDraft,
});

const saveSettings = () =>
    settingsForm
        .transform((data) => ({ ...data, rule_preset: 'custom' }))
        .put(`/games/${props.state.id}/settings`, {
            preserveScroll: true,
            headers: withPlayerToken(token.value),
            onSuccess: () => (editingSettings.value = false),
        });
</script>

<template>
    <Head :title="`Lobby — ${live.name ?? live.code}`" />

    <AppLayout>
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-white sm:text-3xl">
                    {{ live.name || 'Follow Suit' }}
                </h1>
                <p class="mt-1 text-felt-100/80">
                    {{ live.status_label }} · {{ players.length }} of
                    {{ live.maximum_players }} players
                    <span v-if="seatsLeft > 0">
                        · {{ seatsLeft }} seat{{
                            seatsLeft === 1 ? '' : 's'
                        }}
                        free</span
                    >
                </p>
            </div>

            <div class="flex items-center gap-2">
                <span
                    :class="[
                        'rounded-full px-2.5 py-1 text-xs font-semibold',
                        connected
                            ? 'bg-felt-500/25 text-felt-100'
                            : 'bg-white/10 text-felt-200/70',
                    ]"
                >
                    {{ connected ? 'Live' : 'Reconnecting…' }}
                </span>
                <button
                    type="button"
                    class="rounded-lg bg-white/10 px-3 py-2 text-sm text-white hover:bg-white/20"
                    :aria-pressed="muted"
                    @click="toggleMute"
                >
                    {{ muted ? '🔇' : '🔊' }}
                </button>
            </div>
        </div>

        <div class="mt-6 grid gap-4 lg:grid-cols-[1fr_20rem]">
            <div class="space-y-4">
                <AppPanel title="Players">
                    <ul class="space-y-2">
                        <li
                            v-for="player in players"
                            :key="player.id"
                            class="flex items-center gap-3 rounded-xl border border-white/10 bg-felt-950/40 p-3"
                        >
                            <PlayerAvatar
                                :initials="player.initials"
                                :nickname="player.nickname"
                                :connected="player.is_connected"
                            />

                            <div class="min-w-0 flex-1">
                                <p
                                    class="flex flex-wrap items-center gap-1.5 text-sm font-semibold text-white"
                                >
                                    <span class="truncate">{{
                                        player.nickname
                                    }}</span>
                                    <span
                                        v-if="player.id === me?.id"
                                        class="text-xs font-normal text-felt-200/70"
                                        >(you)</span
                                    >
                                    <span
                                        v-if="player.is_host"
                                        class="rounded bg-brass-400/20 px-1.5 py-0.5 text-[10px] font-bold text-brass-300 uppercase"
                                    >
                                        Host
                                    </span>
                                </p>
                                <p
                                    class="mt-0.5 flex flex-wrap gap-x-2 text-xs"
                                >
                                    <span
                                        :class="
                                            player.is_ready
                                                ? 'text-felt-300'
                                                : 'text-felt-200/60'
                                        "
                                    >
                                        {{
                                            player.is_ready
                                                ? '✓ Ready'
                                                : '· Not ready'
                                        }}
                                    </span>
                                    <span
                                        v-if="live.punishment_enabled"
                                        :class="
                                            player.has_acknowledged_punishment
                                                ? 'text-felt-300'
                                                : 'text-brass-300'
                                        "
                                    >
                                        {{
                                            player.has_acknowledged_punishment
                                                ? '✓ Agreed to punishment'
                                                : '· Punishment not agreed'
                                        }}
                                    </span>
                                    <span
                                        :class="
                                            player.is_connected
                                                ? 'text-felt-200/60'
                                                : 'text-red-300'
                                        "
                                    >
                                        {{
                                            player.is_connected
                                                ? '· Connected'
                                                : '· Offline'
                                        }}
                                    </span>
                                </p>
                            </div>

                            <AppButton
                                v-if="isHost && !player.is_host"
                                variant="ghost"
                                size="sm"
                                @click="removePlayer(player.id)"
                            >
                                Remove
                            </AppButton>
                        </li>
                    </ul>

                    <p
                        v-if="seatsLeft > 0"
                        class="mt-3 text-sm text-felt-200/70"
                    >
                        Waiting for {{ seatsLeft }} more player{{
                            seatsLeft === 1 ? '' : 's'
                        }}
                        — share the link to fill the table.
                    </p>
                </AppPanel>

                <PunishmentCard
                    v-if="live.punishment"
                    :punishment="live.punishment"
                    :notice="punishmentNotice"
                >
                </PunishmentCard>

                <AlertBanner
                    v-if="live.punishment && !me?.has_acknowledged_punishment"
                    tone="warning"
                >
                    <p class="font-semibold">
                        Everyone has to agree before the game can start.
                    </p>
                    <AppButton size="sm" class="mt-2" @click="acknowledge"
                        >I agree to the punishment</AppButton
                    >
                </AlertBanner>

                <AppPanel :title="`Rules: ${live.rule_preset_label}`">
                    <template v-if="isHost" #actions>
                        <button
                            type="button"
                            class="text-sm font-semibold text-brass-300 underline underline-offset-4"
                            :aria-expanded="editingSettings"
                            @click="editingSettings = !editingSettings"
                        >
                            {{ editingSettings ? 'Cancel' : 'Change rules' }}
                        </button>
                    </template>

                    <RulesSummary
                        v-if="!editingSettings"
                        :rows="live.rules_summary"
                        columns
                    />
                </AppPanel>

                <form
                    v-if="editingSettings && isHost"
                    class="space-y-4"
                    @submit.prevent="saveSettings"
                >
                    <AppPanel title="Table">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <FormField
                                label="Game name"
                                for="settings-name"
                                :error="settingsForm.errors.name"
                            >
                                <input
                                    id="settings-name"
                                    v-model="settingsForm.name"
                                    type="text"
                                    maxlength="60"
                                    class="field-control"
                                />
                            </FormField>

                            <FormField
                                label="Maximum players"
                                for="settings-max"
                                :error="settingsForm.errors.maximum_players"
                            >
                                <div class="flex gap-2">
                                    <button
                                        v-for="count in [2, 3, 4]"
                                        :key="count"
                                        type="button"
                                        :disabled="count < players.length"
                                        :aria-pressed="
                                            settingsForm.maximum_players ===
                                            count
                                        "
                                        :class="[
                                            'flex-1 rounded-xl py-3 font-semibold transition disabled:opacity-40',
                                            settingsForm.maximum_players ===
                                            count
                                                ? 'bg-brass-400 text-felt-950'
                                                : 'bg-white/10 text-white hover:bg-white/20',
                                        ]"
                                        @click="
                                            settingsForm.maximum_players = count
                                        "
                                    >
                                        {{ count }}
                                    </button>
                                </div>
                            </FormField>
                        </div>
                    </AppPanel>

                    <RulesForm
                        v-model="settingsForm.rules"
                        :options="options"
                        :errors="settingsForm.errors"
                    />

                    <PunishmentForm
                        v-model:enabled="settingsForm.punishment_enabled"
                        v-model:punishment="settingsForm.punishment"
                        :targets="options.punishment_targets"
                        :severities="options.punishment_severities"
                        :examples="[]"
                        :notice="punishmentNotice"
                        :errors="settingsForm.errors"
                    />

                    <AppButton
                        type="submit"
                        size="lg"
                        block
                        :disabled="settingsForm.processing"
                    >
                        {{ settingsForm.processing ? 'Saving…' : 'Save rules' }}
                    </AppButton>
                </form>
            </div>

            <aside class="space-y-4">
                <AppPanel title="Invite your friends">
                    <InviteBox
                        v-if="live.invitation_url"
                        :url="live.invitation_url"
                        :code="live.code"
                        :game-name="live.name"
                    />
                </AppPanel>

                <AppPanel>
                    <template v-if="isHost">
                        <AlertBanner v-if="!live.can_start" tone="warning">
                            <ul class="list-inside list-disc space-y-1">
                                <li
                                    v-for="blocker in live.start_blockers"
                                    :key="blocker"
                                >
                                    {{ blocker }}
                                </li>
                            </ul>
                        </AlertBanner>

                        <AppButton
                            size="lg"
                            block
                            class="mt-3"
                            :disabled="!live.can_start"
                            @click="start"
                        >
                            Start the game
                        </AppButton>
                        <AppButton
                            variant="ghost"
                            block
                            class="mt-2"
                            @click="cancel"
                            >Cancel game</AppButton
                        >
                    </template>

                    <template v-else>
                        <AppButton
                            size="lg"
                            block
                            :variant="me?.is_ready ? 'secondary' : 'primary'"
                            @click="toggleReady"
                        >
                            {{ me?.is_ready ? "I'm not ready" : "I'm ready" }}
                        </AppButton>
                        <p class="mt-3 text-center text-sm text-felt-200/70">
                            Waiting for the host to start the game.
                        </p>
                        <AppButton
                            variant="ghost"
                            block
                            class="mt-2"
                            @click="leave"
                            >Leave the lobby</AppButton
                        >
                    </template>
                </AppPanel>
            </aside>
        </div>
    </AppLayout>
</template>
