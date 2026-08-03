<script setup lang="ts">
import QRCode from 'qrcode';
import { onMounted, ref, watch } from 'vue';

const props = withDefaults(defineProps<{ value: string; size?: number }>(), {
    size: 200,
});

const dataUrl = ref<string>('');
const failed = ref(false);

const render = async () => {
    if (!props.value) {
        return;
    }

    try {
        dataUrl.value = await QRCode.toDataURL(props.value, {
            width: props.size,
            margin: 1,
            color: { dark: '#06261c', light: '#ffffff' },
        });
        failed.value = false;
    } catch {
        failed.value = true;
    }
};

onMounted(render);
watch(() => props.value, render);
</script>

<template>
    <div class="inline-flex flex-col items-center gap-2">
        <img
            v-if="dataUrl"
            :src="dataUrl"
            :width="size"
            :height="size"
            alt="QR code for the game invitation link"
            class="rounded-xl bg-white p-2"
        />
        <p v-else-if="failed" class="text-sm text-felt-200/70">
            The QR code could not be generated.
        </p>
        <div
            v-else
            class="animate-pulse rounded-xl bg-white/20"
            :style="{ width: `${size}px`, height: `${size}px` }"
        />
    </div>
</template>
