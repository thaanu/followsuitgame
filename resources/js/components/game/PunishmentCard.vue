<script setup lang="ts">
import type { PunishmentSummary } from '@/types/game';

defineProps<{ punishment: PunishmentSummary; notice?: string }>();
</script>

<template>
    <div class="rounded-2xl border border-brass-400/40 bg-brass-400/10 p-4">
        <div class="flex items-start gap-3">
            <span class="text-2xl leading-none" aria-hidden="true">{{
                punishment.emoji || '🎯'
            }}</span>
            <div class="min-w-0 flex-1">
                <h3 class="font-semibold text-brass-300">
                    Punishment: {{ punishment.title }}
                </h3>
                <p
                    v-if="punishment.description"
                    class="mt-1 text-sm text-felt-100/90"
                >
                    {{ punishment.description }}
                </p>
                <p class="mt-2 flex flex-wrap gap-2 text-xs">
                    <span
                        class="rounded-full bg-white/10 px-2 py-0.5 text-felt-100"
                    >
                        Applies to: {{ punishment.target_label }}
                    </span>
                    <span
                        v-if="punishment.severity_label"
                        class="rounded-full bg-white/10 px-2 py-0.5 text-felt-100"
                    >
                        {{ punishment.severity_label }}
                    </span>
                </p>
            </div>
        </div>
        <p
            v-if="notice"
            class="mt-3 border-t border-brass-400/20 pt-2 text-xs text-brass-300/80"
        >
            {{ notice }}
        </p>
    </div>
</template>
