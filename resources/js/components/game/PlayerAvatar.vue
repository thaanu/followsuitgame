<script setup lang="ts">
import { computed } from 'vue';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        initials: string;
        nickname: string;
        size?: 'sm' | 'md' | 'lg';
        active?: boolean;
        connected?: boolean;
    }>(),
    { size: 'md', active: false, connected: true },
);

/* A stable colour per nickname keeps players recognisable between screens. */
const PALETTE = [
    'bg-sky-500',
    'bg-violet-500',
    'bg-amber-500',
    'bg-rose-500',
    'bg-teal-500',
    'bg-indigo-500',
];

const colour = computed(() => {
    const sum = [...props.nickname].reduce(
        (total, character) => total + character.charCodeAt(0),
        0,
    );

    return PALETTE[sum % PALETTE.length];
});

const sizeClasses = computed(
    () =>
        ({
            sm: 'size-8 text-xs',
            md: 'size-11 text-sm',
            lg: 'size-14 text-base',
        })[props.size],
);
</script>

<template>
    <span class="relative inline-flex shrink-0">
        <span
            :class="
                cn(
                    'inline-flex items-center justify-center rounded-full font-bold text-white ring-2',
                    sizeClasses,
                    colour,
                    active ? 'animate-turn ring-brass-400' : 'ring-white/20',
                    !connected && 'grayscale',
                )
            "
            aria-hidden="true"
        >
            {{ initials }}
        </span>
        <span
            v-if="!connected"
            class="absolute -right-0.5 -bottom-0.5 rounded-full bg-felt-950 px-1 text-[10px] leading-4"
            :title="`${nickname} is offline`"
            >⚡</span
        >
    </span>
</template>
