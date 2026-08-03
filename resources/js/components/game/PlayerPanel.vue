<script setup lang="ts">
import type { PlayerState } from '@/types/game';
import CardBack from './CardBack.vue';
import PlayerAvatar from './PlayerAvatar.vue';

defineProps<{ player: PlayerState; compact?: boolean }>();
</script>

<template>
    <div
        :class="[
            'panel flex items-center gap-3 p-3 transition',
            player.is_current ? 'border-brass-400/70 bg-brass-400/10' : '',
        ]"
    >
        <PlayerAvatar
            :initials="player.initials"
            :nickname="player.nickname"
            :active="player.is_current"
            :connected="player.is_connected"
            size="sm"
        />

        <div class="min-w-0 flex-1">
            <p
                class="flex items-center gap-1.5 truncate text-sm font-semibold text-white"
            >
                <span class="truncate">{{ player.nickname }}</span>
                <span
                    v-if="player.is_host"
                    class="rounded bg-brass-400/20 px-1.5 py-0.5 text-[10px] font-bold text-brass-300 uppercase"
                >
                    Host
                </span>
            </p>
            <p class="mt-0.5 truncate text-xs text-felt-200/80">
                <span>{{ player.hand_count }} in hand</span>
                <span v-if="!compact">
                    · {{ player.piles_collected }} piles ·
                    {{ player.cards_collected }} collected</span
                >
                <span
                    v-if="player.is_current"
                    class="font-semibold text-brass-300"
                >
                    · playing now</span
                >
                <span
                    v-if="!player.is_connected"
                    class="font-semibold text-red-300"
                >
                    · offline</span
                >
            </p>
        </div>

        <CardBack size="xs" :count="player.hand_count" />
    </div>
</template>
