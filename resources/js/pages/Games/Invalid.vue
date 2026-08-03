<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppButton from '@/components/ui/AppButton.vue';
import AppPanel from '@/components/ui/AppPanel.vue';
import AppLayout from '@/layouts/AppLayout.vue';

const props = defineProps<{
    reason: string;
    message: string;
    gameCode?: string | null;
}>();

const HELP: Record<string, string> = {
    invalid_invitation:
        'Double-check the link or code — it may have been mistyped.',
    expired_invitation:
        'Invitations last 24 hours. Ask the host to create a new game.',
    already_started:
        'Ask the host to add you to the next game, or start a rematch when this one finishes.',
    game_full:
        'Every seat is taken. Ask the host to raise the player limit before starting.',
    game_cancelled: 'The host closed this room. Ask them for a fresh link.',
    game_completed: 'This game has finished. Ask the host for a rematch link.',
};

const help = computed(
    () => HELP[props.reason] ?? 'Ask the host to send a new invitation.',
);
const icon = computed(() =>
    props.reason === 'game_full'
        ? '🪑'
        : props.reason === 'expired_invitation'
          ? '⏳'
          : '🚫',
);
</script>

<template>
    <Head title="Invitation unavailable" />

    <AppLayout narrow back="/" backLabel="Home">
        <AppPanel class="text-center">
            <p class="text-5xl" aria-hidden="true">{{ icon }}</p>
            <h1 class="mt-4 text-xl font-bold text-white">{{ message }}</h1>
            <p class="mt-2 text-felt-100/80">{{ help }}</p>
            <p v-if="gameCode" class="mt-2 font-mono text-sm text-felt-200/60">
                Code: {{ gameCode }}
            </p>

            <div class="mt-6 grid gap-2 sm:grid-cols-2">
                <AppButton href="/games/create">Create your own game</AppButton>
                <AppButton href="/games/join" variant="secondary"
                    >Try another code</AppButton
                >
            </div>
        </AppPanel>
    </AppLayout>
</template>
