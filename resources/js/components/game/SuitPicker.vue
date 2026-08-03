<script setup lang="ts">
import type { CardSuit } from '@/types/game';

const emit = defineEmits<{ choose: [CardSuit]; cancel: [] }>();

defineProps<{ cardLabel: string }>();

const SUITS: {
    value: CardSuit;
    label: string;
    symbol: string;
    red: boolean;
}[] = [
    { value: 'hearts', label: 'Hearts', symbol: '♥', red: true },
    { value: 'diamonds', label: 'Diamonds', symbol: '♦', red: true },
    { value: 'clubs', label: 'Clubs', symbol: '♣', red: false },
    { value: 'spades', label: 'Spades', symbol: '♠', red: false },
];
</script>

<template>
    <div
        class="fixed inset-0 z-50 flex items-end justify-center bg-black/70 p-4 sm:items-center"
        role="dialog"
        aria-modal="true"
        aria-labelledby="suit-picker-title"
    >
        <div class="panel w-full max-w-sm bg-felt-900 p-5">
            <h2 id="suit-picker-title" class="text-lg font-semibold text-white">
                Choose the new suit
            </h2>
            <p class="mt-1 text-sm text-felt-200/80">
                Playing the {{ cardLabel }} changes what everyone must follow.
            </p>

            <div class="mt-4 grid grid-cols-2 gap-3">
                <button
                    v-for="suit in SUITS"
                    :key="suit.value"
                    type="button"
                    class="flex min-h-20 flex-col items-center justify-center gap-1 rounded-xl bg-white text-slate-900 transition hover:bg-slate-100 active:scale-95"
                    @click="emit('choose', suit.value)"
                >
                    <span
                        :class="[
                            'text-3xl leading-none',
                            suit.red ? 'text-suit-red' : 'text-suit-black',
                        ]"
                        aria-hidden="true"
                    >
                        {{ suit.symbol }}
                    </span>
                    <span class="text-sm font-semibold">{{ suit.label }}</span>
                </button>
            </div>

            <button
                type="button"
                class="mt-4 w-full rounded-xl py-2.5 text-sm font-medium text-felt-200 hover:bg-white/10"
                @click="emit('cancel')"
            >
                Cancel
            </button>
        </div>
    </div>
</template>
