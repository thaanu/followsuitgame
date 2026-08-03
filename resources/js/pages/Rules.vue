<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppButton from '@/components/ui/AppButton.vue';
import AppPanel from '@/components/ui/AppPanel.vue';
import AppLayout from '@/layouts/AppLayout.vue';

const BASICS = [
    'The starting player leads any card they like. Its suit becomes the active suit.',
    'Every player after them must play a card of that same suit.',
    'A player who cannot follow the active suit takes the whole pile.',
    'The pile is cleared, and play carries on until somebody meets the winning condition.',
];

const OPTIONS = [
    {
        title: 'Dealing',
        body: 'Split the whole deck evenly, or deal a fixed number of cards and keep the rest as a draw pile.',
    },
    {
        title: 'Being stuck',
        body: 'A player who cannot follow suit can take the pile, draw cards from the deck, or simply lose their turn.',
    },
    {
        title: 'Where collected cards go',
        body: 'Straight back into the hand, into a visible penalty pile, or out of play but still counted against you.',
    },
    {
        title: 'When a pile ends',
        body: 'When somebody is stuck, once everyone has played a card, after a set number of cards, or on a special card.',
    },
    {
        title: 'Special cards',
        body: 'Optionally let the Ace change the suit, the King reverse play, the Queen skip the next player, the Jack grant another turn, the Joker act as any suit, or a chosen rank sweep the pile away.',
    },
    {
        title: 'Winning',
        body: 'First to empty their hand, last player holding cards loses, fewest collected cards or piles, or a set number of rounds.',
    },
    {
        title: 'Ties',
        body: 'Break them on collected cards, on collected piles, share the result, or play a decider.',
    },
    {
        title: 'Turn timer',
        body: 'Off by default. When on, the deadline is set by the server, and a timeout plays a random legal card, takes the pile, or skips the turn.',
    },
];
</script>

<template>
    <Head title="How to play Follow Suit" />

    <AppLayout back="/" backLabel="Home">
        <h1 class="text-2xl font-bold text-white sm:text-3xl">
            How to play Follow Suit
        </h1>
        <p class="mt-1 text-felt-100/80">
            Follow Suit is a card-shedding game for two to four players. The
            rules below are the common ground; your host can change almost all
            of them.
        </p>

        <AppPanel title="The basic game" class="mt-6">
            <ol class="space-y-3">
                <li
                    v-for="(rule, index) in BASICS"
                    :key="rule"
                    class="flex gap-3"
                >
                    <span
                        class="mt-0.5 inline-flex size-6 shrink-0 items-center justify-center rounded-full bg-brass-400 text-xs font-bold text-felt-950"
                        aria-hidden="true"
                    >
                        {{ index + 1 }}
                    </span>
                    <span class="text-felt-100/90">{{ rule }}</span>
                </li>
            </ol>
        </AppPanel>

        <AppPanel title="What your host can change" class="mt-4">
            <dl class="grid gap-4 sm:grid-cols-2">
                <div v-for="option in OPTIONS" :key="option.title">
                    <dt class="font-semibold text-white">{{ option.title }}</dt>
                    <dd class="mt-1 text-sm text-felt-100/80">
                        {{ option.body }}
                    </dd>
                </div>
            </dl>
        </AppPanel>

        <AppPanel title="Punishments" class="mt-4">
            <p class="text-sm text-felt-100/85">
                A host may attach a light-hearted punishment for whoever
                finishes worst — buying the snacks, making the tea, singing a
                song. Every player has to agree to it in the lobby before the
                game can start, and it is shown to everyone throughout.
            </p>
            <p
                class="mt-3 rounded-xl border border-brass-400/30 bg-brass-400/10 px-4 py-3 text-xs text-brass-300"
            >
                Punishments must be safe, legal, respectful and agreed by
                everyone playing. Nothing dangerous, humiliating, harassing,
                illegal, coercive, discriminatory, sexual, or involving alcohol
                or other substances.
            </p>
        </AppPanel>

        <AppPanel title="Fair play" class="mt-4">
            <p class="text-sm text-felt-100/85">
                Every move is checked on the server. Your hand is sent only to
                you, opponents are shown as card counts, and the shuffled deck
                order never leaves the server — so there is nothing useful to
                find in the page source.
            </p>
        </AppPanel>

        <div class="mt-6 grid gap-2 sm:grid-cols-2">
            <AppButton href="/games/create" size="lg">Create a game</AppButton>
            <AppButton href="/games/join" variant="secondary" size="lg"
                >Join a game</AppButton
            >
        </div>
    </AppLayout>
</template>
