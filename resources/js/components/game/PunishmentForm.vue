<script setup lang="ts">
import AppPanel from '@/components/ui/AppPanel.vue';
import FormField from '@/components/ui/FormField.vue';
import SelectField from '@/components/ui/SelectField.vue';
import ToggleField from '@/components/ui/ToggleField.vue';
import type { Option, PunishmentDraft } from '@/types/game';

defineProps<{
    targets: Option[];
    severities: Option[];
    examples: { title: string; emoji: string }[];
    notice: string;
    errors?: Record<string, string>;
}>();

const enabled = defineModel<boolean>('enabled', { required: true });
const punishment = defineModel<PunishmentDraft>('punishment', {
    required: true,
});

const applyExample = (example: { title: string; emoji: string }) => {
    punishment.value.title = example.title;
    punishment.value.emoji = example.emoji;
};
</script>

<template>
    <AppPanel
        title="Punishment"
        subtitle="Optional. Something light-hearted for whoever finishes worst."
    >
        <ToggleField
            id="punishment-enabled"
            v-model="enabled"
            label="Set a punishment for this game"
        />

        <div v-if="enabled" class="mt-4 space-y-4">
            <p
                class="rounded-xl border border-brass-400/30 bg-brass-400/10 px-4 py-3 text-xs text-brass-300"
            >
                {{ notice }}
            </p>

            <div>
                <p class="field-label">Quick suggestions</p>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="example in examples"
                        :key="example.title"
                        type="button"
                        class="rounded-full bg-white/10 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-white/20"
                        @click="applyExample(example)"
                    >
                        {{ example.emoji }} {{ example.title }}
                    </button>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-[1fr_6rem]">
                <FormField
                    label="Punishment title"
                    for="punishment-title"
                    :error="errors?.['punishment.title']"
                >
                    <input
                        id="punishment-title"
                        v-model="punishment.title"
                        type="text"
                        maxlength="80"
                        class="field-control"
                        placeholder="Make the tea"
                    />
                </FormField>

                <FormField
                    label="Emoji"
                    for="punishment-emoji"
                    :error="errors?.['punishment.emoji']"
                >
                    <input
                        id="punishment-emoji"
                        v-model="punishment.emoji"
                        type="text"
                        maxlength="8"
                        class="field-control text-center text-xl"
                        placeholder="🫖"
                    />
                </FormField>
            </div>

            <FormField
                label="Description"
                for="punishment-description"
                :error="errors?.['punishment.description']"
            >
                <textarea
                    id="punishment-description"
                    v-model="punishment.description"
                    rows="3"
                    maxlength="400"
                    class="field-control"
                    placeholder="Whoever loses makes tea for everybody before the next round."
                />
            </FormField>

            <div class="grid gap-4 sm:grid-cols-2">
                <SelectField
                    id="punishment-target"
                    v-model="punishment.target"
                    label="Who gets it"
                    :options="targets"
                    :error="errors?.['punishment.target']"
                />

                <SelectField
                    id="punishment-severity"
                    v-model="punishment.severity"
                    label="Severity"
                    :options="[
                        { value: '', label: 'Not specified' },
                        ...severities,
                    ]"
                    :error="errors?.['punishment.severity']"
                />
            </div>

            <ToggleField
                id="punishment-agreement"
                v-model="punishment.agreement_confirmed"
                label="Everyone playing should agree to this punishment"
                description="Each player has to confirm it in the lobby before the game can start."
            />
            <p
                v-if="errors?.['punishment.agreement_confirmed']"
                class="text-xs font-medium text-red-300"
                role="alert"
            >
                {{ errors['punishment.agreement_confirmed'] }}
            </p>
        </div>
    </AppPanel>
</template>
