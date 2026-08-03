<script setup lang="ts">
import type { RuleSummaryRow } from '@/types/game';

defineProps<{ rows: RuleSummaryRow[]; columns?: boolean }>();
</script>

<template>
    <dl
        :class="[
            'grid gap-x-6 gap-y-2 text-sm',
            columns ? 'sm:grid-cols-2' : '',
        ]"
    >
        <div
            v-for="row in rows"
            :key="row.label"
            class="flex justify-between gap-4 border-b border-white/5 pb-1.5"
        >
            <dt class="shrink-0 text-felt-200/70">{{ row.label }}</dt>
            <dd class="text-right font-medium text-white">{{ row.value }}</dd>
        </div>
    </dl>
</template>
