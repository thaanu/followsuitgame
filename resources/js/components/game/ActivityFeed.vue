<script setup lang="ts">
import { nextTick, ref, watch } from 'vue';
import type { ActivityEntry } from '@/types/game';

const props = defineProps<{ entries: ActivityEntry[] }>();

const list = ref<HTMLElement | null>(null);

watch(
    () => props.entries.length,
    async () => {
        await nextTick();
        list.value?.scrollTo({
            top: list.value.scrollHeight,
            behavior: 'smooth',
        });
    },
);
</script>

<template>
    <div
        ref="list"
        class="max-h-52 space-y-1.5 overflow-y-auto pr-1 text-sm"
        role="log"
        aria-live="polite"
        aria-label="Game activity"
    >
        <p v-if="!entries.length" class="text-felt-200/60">
            Nothing has happened yet.
        </p>
        <p v-for="entry in entries" :key="entry.id" class="text-felt-100/90">
            {{ entry.message }}
        </p>
    </div>
</template>
