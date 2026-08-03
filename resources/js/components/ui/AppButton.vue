<script setup lang="ts">
import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        variant?: 'primary' | 'secondary' | 'ghost' | 'danger';
        size?: 'sm' | 'md' | 'lg';
        block?: boolean;
        /** Render as an Inertia link instead of a button. */
        href?: string;
        method?: 'get' | 'post' | 'put' | 'delete';
        type?: 'button' | 'submit';
        disabled?: boolean;
    }>(),
    { variant: 'primary', size: 'md', type: 'button', block: false, disabled: false },
);

const classes = computed(() =>
    cn(
        'inline-flex items-center justify-center gap-2 rounded-xl text-center font-semibold transition',
        'disabled:cursor-not-allowed disabled:opacity-50',
        props.block && 'w-full',
        {
            sm: 'min-h-9 px-3 py-2 text-sm',
            md: 'min-h-11 px-4 py-2.5 text-sm',
            lg: 'min-h-14 px-6 py-3.5 text-base',
        }[props.size],
        {
            primary: 'bg-brass-400 text-felt-950 hover:bg-brass-300 active:scale-[0.98]',
            secondary: 'bg-white/10 text-white hover:bg-white/20 active:scale-[0.98]',
            ghost: 'text-felt-100 hover:bg-white/10',
            danger: 'bg-suit-red/90 text-white hover:bg-suit-red active:scale-[0.98]',
        }[props.variant],
    ),
);
</script>

<template>
    <Link v-if="href" :href="href" :method="method" :class="classes">
        <slot />
    </Link>
    <button v-else :class="classes" :type="type" :disabled="disabled">
        <slot />
    </button>
</template>
