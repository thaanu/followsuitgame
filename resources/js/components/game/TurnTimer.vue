<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    secondsRemaining: number | null;
    totalSeconds: number | null;
    label?: string;
}>();

const percentage = computed(() => {
    if (props.secondsRemaining === null || !props.totalSeconds) {
        return 0;
    }

    return Math.min(
        100,
        Math.max(0, (props.secondsRemaining / props.totalSeconds) * 100),
    );
});

const urgent = computed(
    () => props.secondsRemaining !== null && props.secondsRemaining <= 5,
);
</script>

<template>
    <div
        v-if="secondsRemaining !== null"
        class="flex items-center gap-2"
        role="timer"
        :aria-label="label ?? 'Time left this turn'"
    >
        <div class="h-1.5 w-20 overflow-hidden rounded-full bg-white/15">
            <div
                :class="[
                    'h-full rounded-full transition-all duration-300',
                    urgent ? 'bg-suit-red' : 'bg-brass-400',
                ]"
                :style="{ width: `${percentage}%` }"
            />
        </div>
        <span
            :class="[
                'font-mono text-sm tabular-nums',
                urgent ? 'font-bold text-red-300' : 'text-felt-100',
            ]"
        >
            {{ secondsRemaining }}s
        </span>
    </div>
</template>
