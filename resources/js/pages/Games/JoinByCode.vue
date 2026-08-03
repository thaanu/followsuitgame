<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppButton from '@/components/ui/AppButton.vue';
import AppPanel from '@/components/ui/AppPanel.vue';
import FormField from '@/components/ui/FormField.vue';
import AppLayout from '@/layouts/AppLayout.vue';

const code = ref('');

const normalised = computed(() =>
    code.value
        .toUpperCase()
        .replace(/[^A-Z2-9]/g, '')
        .slice(0, 6),
);
const valid = computed(() => normalised.value.length === 6);

const submit = () => {
    if (valid.value) {
        router.get(`/games/join/${normalised.value}`);
    }
};
</script>

<template>
    <Head title="Join a game" />

    <AppLayout narrow back="/" backLabel="Home">
        <h1 class="text-center text-2xl font-bold text-white sm:text-3xl">
            Join a game
        </h1>
        <p class="mt-1 text-center text-felt-100/80">
            Enter the six-character code your host shared.
        </p>

        <AppPanel class="mt-6">
            <form @submit.prevent="submit">
                <FormField
                    label="Game code"
                    for="code"
                    hint="Letters and numbers, for example ABCD12."
                >
                    <input
                        id="code"
                        v-model="code"
                        type="text"
                        autocapitalize="characters"
                        autocomplete="off"
                        maxlength="6"
                        required
                        autofocus
                        class="field-control text-center font-mono text-3xl font-bold tracking-[0.4em] uppercase"
                        placeholder="ABCD12"
                    />
                </FormField>

                <AppButton
                    type="submit"
                    size="lg"
                    block
                    class="mt-4"
                    :disabled="!valid"
                    >Continue</AppButton
                >
            </form>
        </AppPanel>

        <p class="mt-4 text-center text-sm text-felt-200/70">
            Got a link instead? Just open it and you will land straight in the
            lobby.
        </p>
    </AppLayout>
</template>
