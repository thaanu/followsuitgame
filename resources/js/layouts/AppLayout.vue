<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

withDefaults(
    defineProps<{
        narrow?: boolean;
        back?: string | null;
        backLabel?: string;
    }>(),
    {
        narrow: false,
        back: null,
        backLabel: 'Back',
    },
);
</script>

<template>
    <div class="felt-surface min-h-dvh">
        <header class="border-b border-white/10">
            <div
                class="mx-auto flex max-w-5xl items-center justify-between gap-4 px-4 py-4"
            >
                <Link
                    href="/"
                    class="flex items-center gap-2 font-bold tracking-tight text-white"
                >
                    <span class="text-xl" aria-hidden="true">🂡</span>
                    <span>Follow Suit</span>
                </Link>

                <nav class="flex items-center gap-1 text-sm">
                    <Link
                        v-if="back"
                        :href="back"
                        class="rounded-lg px-3 py-2 text-felt-100 transition hover:bg-white/10"
                    >
                        {{ backLabel }}
                    </Link>
                    <Link
                        href="/rules"
                        class="rounded-lg px-3 py-2 text-felt-100 transition hover:bg-white/10"
                    >
                        How to play
                    </Link>
                </nav>
            </div>
        </header>

        <main
            :class="[
                'mx-auto px-4 py-6 sm:py-10',
                narrow ? 'max-w-xl' : 'max-w-5xl',
            ]"
        >
            <slot />
        </main>

        <footer
            class="border-t border-white/10 py-6 text-center text-xs text-felt-200/50"
        >
            Follow Suit — play nicely, and keep punishments kind.
        </footer>
    </div>
</template>
