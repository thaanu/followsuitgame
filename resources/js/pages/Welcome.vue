<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppButton from '@/components/ui/AppButton.vue';
import AppLayout from '@/layouts/AppLayout.vue';

const HOW_IT_WORKS = [
    {
        title: 'Create a room',
        body: 'Pick your rules, add an optional punishment, and get a code and QR to share.',
    },
    {
        title: 'Friends join',
        body: 'They open the link, choose a nickname and appear in your lobby straight away.',
    },
    {
        title: 'Follow the suit',
        body: 'Match the suit that was led. If you cannot, the whole pile is yours.',
    },
];
</script>

<template>
    <Head title="Play Follow Suit" />

    <AppLayout>
        <section class="text-center">
            <p
                class="text-sm font-semibold tracking-[0.2em] text-brass-400 uppercase"
            >
                Multiplayer card game
            </p>
            <h1
                class="mt-3 text-4xl font-black tracking-tight text-white sm:text-6xl"
            >
                Follow Suit
            </h1>
            <p class="mx-auto mt-4 max-w-xl text-lg text-felt-100/85">
                Shed your cards by matching the suit that was led. Miss it, and
                you pick up the pile. Two to four players, no sign-up, built for
                phones.
            </p>

            <div
                class="mx-auto mt-8 flex max-w-sm flex-col gap-3 sm:max-w-none sm:flex-row sm:justify-center"
            >
                <AppButton href="/games/create" size="lg"
                    >Create a game</AppButton
                >
                <AppButton href="/games/join" variant="secondary" size="lg"
                    >Join with a code</AppButton
                >
            </div>
        </section>

        <section class="mt-14 grid gap-4 sm:grid-cols-3">
            <div
                v-for="(step, index) in HOW_IT_WORKS"
                :key="step.title"
                class="panel p-5"
            >
                <span
                    class="inline-flex size-8 items-center justify-center rounded-full bg-brass-400 font-bold text-felt-950"
                    aria-hidden="true"
                >
                    {{ index + 1 }}
                </span>
                <h2 class="mt-3 font-semibold text-white">{{ step.title }}</h2>
                <p class="mt-1 text-sm text-felt-100/80">{{ step.body }}</p>
            </div>
        </section>

        <section class="panel mt-10 p-6 text-center">
            <h2 class="text-xl font-semibold text-white">
                Your table, your rules
            </h2>
            <p class="mx-auto mt-2 max-w-2xl text-sm text-felt-100/80">
                Every group plays Follow Suit a little differently, so nothing
                is hardcoded. Choose how cards are dealt, what happens when
                somebody is stuck, whether Kings reverse play, how the game is
                won, and how ties are broken. Start from a preset or build your
                own.
            </p>
            <Link
                href="/rules"
                class="mt-4 inline-block font-semibold text-brass-300 underline underline-offset-4"
            >
                Read the full rules
            </Link>
        </section>
    </AppLayout>
</template>
