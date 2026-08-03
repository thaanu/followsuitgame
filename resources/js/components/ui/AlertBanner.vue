<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(defineProps<{ tone?: 'info' | 'warning' | 'danger' | 'success' }>(), { tone: 'info' });

const toneClasses = computed(
    () =>
        ({
            info: 'border-sky-400/30 bg-sky-400/10 text-sky-100',
            warning: 'border-brass-400/40 bg-brass-400/10 text-brass-300',
            danger: 'border-red-400/40 bg-red-400/10 text-red-100',
            success: 'border-felt-300/40 bg-felt-300/10 text-felt-100',
        })[props.tone],
);
</script>

<template>
    <div :class="['flex gap-3 rounded-xl border px-4 py-3 text-sm', toneClasses]" role="status">
        <slot name="icon" />
        <div class="min-w-0 flex-1"><slot /></div>
    </div>
</template>
