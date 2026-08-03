<script setup lang="ts">
defineProps<{ label: string; description?: string; id?: string }>();
const model = defineModel<boolean>({ required: true });
</script>

<template>
    <label
        :for="id"
        class="flex cursor-pointer items-start gap-3 rounded-xl border border-white/10 bg-felt-950/40 p-3 transition hover:border-white/20"
    >
        <input
            :id="id"
            v-model="model"
            type="checkbox"
            class="mt-0.5 size-5 shrink-0 rounded border-white/30 bg-felt-950 text-brass-500 focus:ring-brass-400"
        />
        <span class="min-w-0">
            <span class="block text-sm font-medium text-white">{{ label }}</span>
            <span v-if="description" class="mt-0.5 block text-xs text-felt-200/70">{{ description }}</span>
        </span>
    </label>
</template>
