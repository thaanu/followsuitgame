import { onBeforeUnmount, onMounted } from 'vue';
import { withPlayerToken } from './usePlayerToken';

/**
 * Tells the server this browser is still watching, and notices when the game has
 * moved on without us — which is the safety net if a WebSocket message is missed.
 */
export function useHeartbeat(options: {
    url: string;
    token: string | null;
    seconds: number;
    currentVersion: () => number;
    onBehind: () => void;
}) {
    let timer: ReturnType<typeof setInterval> | null = null;

    const ping = async () => {
        if (!options.token || document.hidden) {
            return;
        }

        try {
            const response = await fetch(options.url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN':
                        document.querySelector<HTMLMetaElement>(
                            'meta[name="csrf-token"]',
                        )?.content ?? '',
                    ...withPlayerToken(options.token),
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                return;
            }

            const body = (await response.json()) as { state_version: number };

            if (body.state_version > options.currentVersion()) {
                options.onBehind();
            }
        } catch {
            // Offline: the next tick will try again.
        }
    };

    const onVisible = () => {
        if (!document.hidden) {
            void ping();
        }
    };

    onMounted(() => {
        void ping();
        timer = setInterval(
            () => void ping(),
            Math.max(5, options.seconds) * 1000,
        );
        document.addEventListener('visibilitychange', onVisible);
    });

    onBeforeUnmount(() => {
        if (timer) {
            clearInterval(timer);
        }

        document.removeEventListener('visibilitychange', onVisible);
    });

    return { ping };
}
