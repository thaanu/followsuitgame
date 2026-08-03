<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { onMounted } from 'vue';
import AppButton from '@/components/ui/AppButton.vue';
import AppPanel from '@/components/ui/AppPanel.vue';
import AppLayout from '@/layouts/AppLayout.vue';

const props = defineProps<{
    game: {
        id: string;
        code: string;
        name: string | null;
        status: string;
        status_label: string;
    };
    found: boolean;
    nickname: string | null;
}>();

const destination = () => {
    if (props.game.status === 'in_progress') {
        return `/games/${props.game.id}/table`;
    }

    if (props.game.status === 'lobby') {
        return `/games/${props.game.id}/lobby`;
    }

    return `/games/${props.game.id}/results`;
};

/* A seat we can prove ownership of goes straight back into the game. */
onMounted(() => {
    if (props.found) {
        setTimeout(() => router.visit(destination()), 600);
    }
});
</script>

<template>
    <Head title="Reconnecting" />

    <AppLayout narrow>
        <AppPanel class="text-center">
            <template v-if="found">
                <div
                    class="mx-auto size-10 animate-spin rounded-full border-4 border-white/20 border-t-brass-400"
                    aria-hidden="true"
                />
                <h1 class="mt-4 text-xl font-bold text-white">
                    Welcome back, {{ nickname }}
                </h1>
                <p class="mt-2 text-felt-100/80">
                    Restoring your hand and the table…
                </p>
                <AppButton
                    :href="destination()"
                    variant="secondary"
                    class="mt-5"
                    >Go now</AppButton
                >
            </template>

            <template v-else>
                <p class="text-5xl" aria-hidden="true">🔍</p>
                <h1 class="mt-4 text-xl font-bold text-white">
                    We could not find your seat
                </h1>
                <p class="mt-2 text-felt-100/80">
                    This browser does not hold a token for game {{ game.code }}.
                    If you were playing on another device, open the game there.
                    Otherwise ask the host for a new invitation.
                </p>
                <div class="mt-6 grid gap-2 sm:grid-cols-2">
                    <AppButton :href="`/games/join/${game.code}`"
                        >Join this game</AppButton
                    >
                    <AppButton href="/" variant="secondary">Home</AppButton>
                </div>
            </template>
        </AppPanel>
    </AppLayout>
</template>
