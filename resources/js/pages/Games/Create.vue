<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import PunishmentForm from '@/components/game/PunishmentForm.vue';
import RulesForm from '@/components/game/RulesForm.vue';
import AlertBanner from '@/components/ui/AlertBanner.vue';
import AppButton from '@/components/ui/AppButton.vue';
import AppPanel from '@/components/ui/AppPanel.vue';
import FormField from '@/components/ui/FormField.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { deepClone } from '@/lib/utils';
import type {
    Preset,
    PunishmentDraft,
    RuleOptions,
    RuleSet,
} from '@/types/game';

const props = defineProps<{
    options: RuleOptions;
    presets: Preset[];
    defaults: RuleSet;
    punishmentNotice: string;
    punishmentExamples: { title: string; emoji: string }[];
}>();

const showAdvanced = ref(false);

const emptyPunishment = (): PunishmentDraft => ({
    title: '',
    description: '',
    emoji: '',
    severity: '',
    target:
        props.options.punishment_targets[0]?.value ?? 'most_cards_collected',
    agreement_confirmed: false,
});

const form = useForm({
    nickname: '',
    name: '',
    maximum_players: 4,
    rule_preset: props.presets[0]?.value ?? 'classic',
    rules: deepClone(props.defaults) as RuleSet,
    punishment_enabled: false,
    punishment: emptyPunishment(),
});

/* Choosing a named preset swaps in that preset's rules; the custom preset keeps
   whatever the host has already tweaked. */
watch(
    () => form.rule_preset,
    (value) => {
        const preset = props.presets.find(
            (candidate) => candidate.value === value,
        );

        if (preset && value !== 'custom') {
            form.rules = deepClone(preset.rules);
        }

        if (value === 'custom') {
            showAdvanced.value = true;
        }
    },
);

/* Editing any rule moves the host onto the custom preset so their change sticks. */
watch(
    () => form.rules,
    () => {
        if (form.rule_preset !== 'custom') {
            form.rule_preset = 'custom';
        }
    },
    { deep: true },
);

const submit = () => form.post('/games', { preserveScroll: true });
</script>

<template>
    <Head title="Create a game" />

    <AppLayout back="/" backLabel="Home">
        <h1 class="text-2xl font-bold text-white sm:text-3xl">Create a game</h1>
        <p class="mt-1 text-felt-100/80">
            Set it up, then share the link or QR code with your friends.
        </p>

        <form class="mt-6 space-y-4" @submit.prevent="submit">
            <AlertBanner v-if="Object.keys(form.errors).length" tone="danger">
                Please check the highlighted fields below.
            </AlertBanner>

            <AppPanel title="You and the table">
                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField
                        label="Your nickname"
                        for="nickname"
                        :error="form.errors.nickname"
                    >
                        <input
                            id="nickname"
                            v-model="form.nickname"
                            type="text"
                            maxlength="20"
                            autocomplete="nickname"
                            required
                            class="field-control"
                            placeholder="Ahmed"
                        />
                    </FormField>

                    <FormField
                        label="Game name (optional)"
                        for="game-name"
                        :error="form.errors.name"
                    >
                        <input
                            id="game-name"
                            v-model="form.name"
                            type="text"
                            maxlength="60"
                            class="field-control"
                            placeholder="Friday night"
                        />
                    </FormField>

                    <FormField
                        label="Maximum players"
                        for="max-players"
                        :error="form.errors.maximum_players"
                    >
                        <div class="flex gap-2">
                            <button
                                v-for="count in [2, 3, 4]"
                                :key="count"
                                type="button"
                                :aria-pressed="form.maximum_players === count"
                                :class="[
                                    'flex-1 rounded-xl py-3 font-semibold transition',
                                    form.maximum_players === count
                                        ? 'bg-brass-400 text-felt-950'
                                        : 'bg-white/10 text-white hover:bg-white/20',
                                ]"
                                @click="form.maximum_players = count"
                            >
                                {{ count }}
                            </button>
                        </div>
                    </FormField>
                </div>
            </AppPanel>

            <AppPanel
                title="Rules"
                subtitle="Start from a preset, or open the full settings."
            >
                <div class="grid gap-3 sm:grid-cols-3">
                    <button
                        v-for="preset in presets"
                        :key="preset.value"
                        type="button"
                        :aria-pressed="form.rule_preset === preset.value"
                        :class="[
                            'rounded-xl border p-4 text-left transition',
                            form.rule_preset === preset.value
                                ? 'border-brass-400 bg-brass-400/15'
                                : 'border-white/10 bg-felt-950/40 hover:border-white/25',
                        ]"
                        @click="form.rule_preset = preset.value"
                    >
                        <span class="block font-semibold text-white">{{
                            preset.label
                        }}</span>
                        <span class="mt-1 block text-xs text-felt-200/80">{{
                            preset.description
                        }}</span>
                    </button>
                </div>

                <button
                    type="button"
                    class="mt-4 text-sm font-semibold text-brass-300 underline underline-offset-4"
                    :aria-expanded="showAdvanced"
                    @click="showAdvanced = !showAdvanced"
                >
                    {{ showAdvanced ? 'Hide' : 'Show' }} all rule settings
                </button>
            </AppPanel>

            <RulesForm
                v-if="showAdvanced"
                v-model="form.rules"
                :options="options"
                :errors="form.errors"
            />

            <PunishmentForm
                v-model:enabled="form.punishment_enabled"
                v-model:punishment="form.punishment"
                :targets="options.punishment_targets"
                :severities="options.punishment_severities"
                :examples="punishmentExamples"
                :notice="punishmentNotice"
                :errors="form.errors"
            />

            <div class="sticky bottom-4 z-10">
                <AppButton
                    type="submit"
                    size="lg"
                    block
                    :disabled="form.processing"
                >
                    {{ form.processing ? 'Creating…' : 'Create game' }}
                </AppButton>
            </div>
        </form>
    </AppLayout>
</template>
