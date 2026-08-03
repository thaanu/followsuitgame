<script setup lang="ts">
defineProps<{ title?: string; subtitle?: string }>();
</script>

<template>
    <section class="panel p-4 sm:p-5">
        <header v-if="title || $slots.actions" class="mb-3 flex items-start justify-between gap-3">
            <div>
                <h2 v-if="title" class="text-base font-semibold text-white">{{ title }}</h2>
                <p v-if="subtitle" class="mt-0.5 text-sm text-felt-200/80">{{ subtitle }}</p>
            </div>
            <slot name="actions" />
        </header>
        <slot />
    </section>
</template>
