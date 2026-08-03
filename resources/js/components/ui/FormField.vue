<script setup lang="ts">
defineProps<{ label: string; hint?: string; error?: string; for?: string }>();
</script>

<template>
    <div>
        <label :for="$props.for" class="field-label">{{ label }}</label>
        <slot />
        <p v-if="hint && !error" class="field-hint">{{ hint }}</p>
        <p v-if="error" class="mt-1 text-xs font-medium text-red-300" role="alert">{{ error }}</p>
    </div>
</template>
