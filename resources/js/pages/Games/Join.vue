<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import PunishmentCard from '@/components/game/PunishmentCard.vue';
import RulesSummary from '@/components/game/RulesSummary.vue';
import AlertBanner from '@/components/ui/AlertBanner.vue';
import AppButton from '@/components/ui/AppButton.vue';
import AppPanel from '@/components/ui/AppPanel.vue';
import FormField from '@/components/ui/FormField.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { PunishmentSummary, RuleSummaryRow } from '@/types/game';

const props = defineProps<{
    game: {
        code: string;
        name: string | null;
        host: string | null;
        player_count: number;
        maximum_players: number;
        rules_summary: RuleSummaryRow[];
        rule_preset_label: string;
        punishment: PunishmentSummary | null;
    };
    token: string | null;
    takenNicknames: string[];
    punishmentNotice: string;
}>();

const showRules = ref(false);

const form = useForm({
    nickname: '',
    token: props.token ?? '',
    player_token: '',
});

/* If this browser has played in this game before, offer the seat straight back. */
onMounted(() => {
    try {
        const stored = Object.keys(window.localStorage).find((key) =>
            key.startsWith('follow-suit.player.'),
        );

        if (stored) {
            form.player_token = window.localStorage.getItem(stored) ?? '';
        }
    } catch {
        // Storage is unavailable; joining fresh still works.
    }
});

const taken = computed(() =>
    props.takenNicknames.some(
        (name) => name.toLowerCase() === form.nickname.trim().toLowerCase(),
    ),
);

const submit = () =>
    form.post(`/games/join/${props.game.code}`, { preserveScroll: true });
</script>

<template>
    <Head :title="`Join ${game.name ?? 'the game'}`" />

    <AppLayout narrow back="/" backLabel="Home">
        <div class="text-center">
            <p class="text-sm tracking-[0.2em] text-brass-400 uppercase">
                You have been invited
            </p>
            <h1 class="mt-2 text-2xl font-bold text-white sm:text-3xl">
                {{ game.name || 'Follow Suit' }}
            </h1>
            <p class="mt-1 text-felt-100/80">
                <span v-if="game.host">Hosted by {{ game.host }} · </span>
                {{ game.player_count }} of {{ game.maximum_players }} seats
                taken
            </p>
        </div>

        <form class="mt-6 space-y-4" @submit.prevent="submit">
            <AppPanel>
                <FormField
                    label="Choose a nickname"
                    for="nickname"
                    hint="This is how everyone at the table will see you."
                    :error="form.errors.nickname"
                >
                    <input
                        id="nickname"
                        v-model="form.nickname"
                        type="text"
                        maxlength="20"
                        autocomplete="nickname"
                        required
                        autofocus
                        class="field-control text-lg"
                        placeholder="Mahu"
                    />
                </FormField>

                <AlertBanner v-if="taken" tone="warning" class="mt-3">
                    Somebody in this game is already called “{{
                        form.nickname
                    }}”. Pick another name.
                </AlertBanner>

                <AppButton
                    type="submit"
                    size="lg"
                    block
                    class="mt-4"
                    :disabled="form.processing || taken"
                >
                    {{ form.processing ? 'Joining…' : 'Join the game' }}
                </AppButton>
            </AppPanel>

            <PunishmentCard
                v-if="game.punishment"
                :punishment="game.punishment"
                :notice="punishmentNotice"
            />

            <AppPanel :title="`Rules: ${game.rule_preset_label}`">
                <template #actions>
                    <button
                        type="button"
                        class="text-sm font-semibold text-brass-300 underline underline-offset-4"
                        :aria-expanded="showRules"
                        @click="showRules = !showRules"
                    >
                        {{ showRules ? 'Hide' : 'Show' }}
                    </button>
                </template>

                <RulesSummary v-if="showRules" :rows="game.rules_summary" />
                <p v-else class="text-sm text-felt-200/70">
                    Tap “Show” to read the rules for this table.
                </p>
            </AppPanel>
        </form>
    </AppLayout>
</template>
