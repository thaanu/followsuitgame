<script setup lang="ts">
import { computed } from 'vue';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{ size?: 'xs' | 'sm' | 'md'; count?: number }>(),
    { size: 'sm' },
);

const sizeClasses = computed(
    () =>
        ({
            xs: 'h-10 w-7 rounded',
            sm: 'h-16 w-11 rounded-lg',
            md: 'h-24 w-16 rounded-xl',
        })[props.size],
);
</script>

<template>
    <div
        :class="
            cn(
                'flex items-center justify-center border border-felt-300/30 shadow-md',
                'bg-[repeating-linear-gradient(45deg,#12513a_0_6px,#0f4331_6px_12px)]',
                sizeClasses,
            )
        "
        :aria-label="
            count !== undefined ? `${count} hidden cards` : 'Face-down card'
        "
        role="img"
    >
        <span
            v-if="count !== undefined"
            class="text-xs font-bold text-felt-100"
            >{{ count }}</span
        >
    </div>
</template>
