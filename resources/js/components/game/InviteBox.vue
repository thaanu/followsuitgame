<script setup lang="ts">
import { ref } from 'vue';
import AppButton from '@/components/ui/AppButton.vue';
import QrCode from './QrCode.vue';

const props = defineProps<{
    url: string;
    code: string;
    gameName?: string | null;
}>();

const copied = ref(false);
const showQr = ref(false);
const canShare = typeof navigator !== 'undefined' && 'share' in navigator;

const copy = async () => {
    try {
        await navigator.clipboard.writeText(props.url);
        copied.value = true;
        setTimeout(() => (copied.value = false), 2000);
    } catch {
        // Clipboard access is blocked; the link is on screen to copy by hand.
    }
};

const share = async () => {
    try {
        await navigator.share({
            title: props.gameName
                ? `Follow Suit — ${props.gameName}`
                : 'Follow Suit',
            text: `Join my Follow Suit game. Code: ${props.code}`,
            url: props.url,
        });
    } catch {
        // The player dismissed the share sheet.
    }
};
</script>

<template>
    <div class="space-y-3">
        <div
            class="flex items-center justify-between gap-3 rounded-xl border border-white/10 bg-felt-950/50 px-4 py-3"
        >
            <div class="min-w-0">
                <p class="text-xs tracking-wide text-felt-200/70 uppercase">
                    Game code
                </p>
                <p
                    class="font-mono text-2xl font-bold tracking-[0.3em] text-brass-300"
                >
                    {{ code }}
                </p>
            </div>

            <!-- A small decorative fan of card backs. -->
            <span
                class="relative hidden h-10 w-16 shrink-0 sm:block"
                aria-hidden="true"
            >
                <span
                    v-for="(rotation, index) in [-14, 0, 14]"
                    :key="rotation"
                    class="absolute inset-y-0 left-1/2 w-7 rounded border border-felt-300/30 bg-[repeating-linear-gradient(45deg,#12513a_0_4px,#0f4331_4px_8px)]"
                    :style="{
                        transform: `translateX(-50%) rotate(${rotation}deg)`,
                        zIndex: index,
                    }"
                />
            </span>
        </div>

        <p
            class="truncate rounded-lg bg-black/25 px-3 py-2 font-mono text-xs text-felt-100/80"
            :title="url"
        >
            {{ url }}
        </p>

        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
            <AppButton variant="primary" @click="copy">
                {{ copied ? 'Copied!' : 'Copy link' }}
            </AppButton>
            <AppButton v-if="canShare" variant="secondary" @click="share"
                >Share</AppButton
            >
            <AppButton
                variant="secondary"
                :aria-expanded="showQr"
                @click="showQr = !showQr"
            >
                {{ showQr ? 'Hide QR' : 'Show QR' }}
            </AppButton>
        </div>

        <div v-if="showQr" class="flex justify-center pt-1">
            <QrCode :value="url" :size="192" />
        </div>
    </div>
</template>
