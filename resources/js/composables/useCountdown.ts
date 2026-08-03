import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import type { Ref } from 'vue';

/**
 * A display-only countdown to a server-supplied deadline.
 *
 * The server decides when a turn has actually expired; this only draws the clock,
 * corrected once for the offset between the browser and server clocks.
 */
export function useCountdown(
    deadline: Ref<string | null>,
    serverTime: Ref<string>,
) {
    const now = ref(Date.now());
    let timer: ReturnType<typeof setInterval> | null = null;

    const offset = computed(() => {
        const server = Date.parse(serverTime.value);

        return Number.isNaN(server) ? 0 : server - Date.now();
    });

    const secondsRemaining = computed<number | null>(() => {
        if (!deadline.value) {
            return null;
        }

        const end = Date.parse(deadline.value);

        if (Number.isNaN(end)) {
            return null;
        }

        return Math.max(
            0,
            Math.round((end - (now.value + offset.value)) / 1000),
        );
    });

    onMounted(() => {
        timer = setInterval(() => (now.value = Date.now()), 250);
    });

    onBeforeUnmount(() => {
        if (timer) {
            clearInterval(timer);
        }
    });

    return { secondsRemaining };
}
