<script setup lang="ts">
import { computed } from 'vue';
import { cn } from '@/lib/utils';
import type { Card } from '@/types/game';

const props = withDefaults(
    defineProps<{
        card: Card;
        size?: 'sm' | 'md' | 'lg';
        selectable?: boolean;
        selected?: boolean;
        disabled?: boolean;
        dealt?: boolean;
    }>(),
    {
        size: 'md',
        selectable: false,
        selected: false,
        disabled: false,
        dealt: false,
    },
);

const SUIT_SYMBOL: Record<string, string> = {
    hearts: '♥',
    diamonds: '♦',
    clubs: '♣',
    spades: '♠',
    none: '★',
};

const RANK_LABEL: Record<string, string> = {
    ace: 'A',
    two: '2',
    three: '3',
    four: '4',
    five: '5',
    six: '6',
    seven: '7',
    eight: '8',
    nine: '9',
    ten: '10',
    jack: 'J',
    queen: 'Q',
    king: 'K',
    joker: '★',
};

const symbol = computed(() => SUIT_SYMBOL[props.card.suit] ?? '★');
const rank = computed(() => RANK_LABEL[props.card.rank] ?? '?');
const isJoker = computed(() => props.card.rank === 'joker');

const sizeClasses = computed(
    () =>
        ({
            sm: 'h-16 w-11 text-xs rounded-lg',
            md: 'h-24 w-16 text-base rounded-xl',
            lg: 'h-32 w-22 text-lg rounded-2xl',
        })[props.size],
);
</script>

<template>
    <component
        :is="selectable ? 'button' : 'div'"
        :type="selectable ? 'button' : undefined"
        :disabled="selectable ? disabled : undefined"
        :aria-pressed="selectable ? selected : undefined"
        :aria-label="`${card.label}${disabled ? ' — cannot be played' : ''}`"
        :class="
            cn(
                'relative flex shrink-0 flex-col justify-between border bg-white p-1.5 shadow-lg transition',
                sizeClasses,
                card.is_red ? 'text-suit-red' : 'text-suit-black',
                selected
                    ? '-translate-y-4 border-brass-400 ring-4 ring-brass-400/60'
                    : 'border-slate-300',
                selectable &&
                    !disabled &&
                    'cursor-pointer hover:-translate-y-2 active:translate-y-0',
                disabled && 'opacity-45 saturate-50',
                dealt && 'animate-card-deal',
            )
        "
    >
        <span class="flex items-center gap-0.5 leading-none font-bold">
            <span>{{ rank }}</span>
            <span aria-hidden="true">{{ symbol }}</span>
        </span>

        <span
            :class="[
                'self-center leading-none',
                size === 'sm'
                    ? 'text-xl'
                    : size === 'md'
                      ? 'text-3xl'
                      : 'text-4xl',
            ]"
            aria-hidden="true"
        >
            {{ isJoker ? '🃏' : symbol }}
        </span>

        <span
            class="flex rotate-180 items-center gap-0.5 leading-none font-bold"
            aria-hidden="true"
        >
            <span>{{ rank }}</span>
            <span>{{ symbol }}</span>
        </span>

        <!-- Legality is also announced through the label and the dimmed state, never colour alone. -->
        <span
            v-if="disabled"
            class="absolute inset-x-0 bottom-1 text-center text-[9px] font-semibold tracking-wide text-slate-500 uppercase"
        >
            Blocked
        </span>
    </component>
</template>
