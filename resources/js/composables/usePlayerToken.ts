import { router, usePage } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';

const STORAGE_PREFIX = 'follow-suit.player.';

/**
 * The opaque token that proves this browser owns a seat.
 *
 * The server mirrors the token into the session, but we also keep it in local
 * storage so a player who comes back after the session cookie has gone can still
 * reclaim their seat.
 */
export function usePlayerToken(gameId: string, initial?: string | null) {
    const token = ref<string | null>(initial ?? null);

    const storageKey = () => `${STORAGE_PREFIX}${gameId}`;

    const read = (): string | null => {
        try {
            return window.localStorage.getItem(storageKey());
        } catch {
            return null;
        }
    };

    const write = (value: string) => {
        try {
            window.localStorage.setItem(storageKey(), value);
        } catch {
            // Private browsing modes can refuse writes; the session cookie still works.
        }
    };

    const clear = () => {
        try {
            window.localStorage.removeItem(storageKey());
        } catch {
            // Nothing to do.
        }
    };

    onMounted(() => {
        const page = usePage();
        const flashed = (
            page.props.flash as { playerToken?: string } | undefined
        )?.playerToken;

        if (flashed) {
            token.value = flashed;
        }

        if (token.value) {
            write(token.value);

            return;
        }

        const stored = read();

        if (stored) {
            token.value = stored;
        }
    });

    return { token, clear, write };
}

/**
 * Attach the player token to every Inertia request this page makes, so the server
 * can identify the player even when the session cookie is missing.
 */
export function withPlayerToken(token: string | null): Record<string, string> {
    return token ? { 'X-Player-Token': token } : {};
}

/**
 * A short random key used to make a move submission safe to retry.
 */
export function idempotencyKey(prefix: string): string {
    const random =
        typeof crypto !== 'undefined' && 'randomUUID' in crypto
            ? crypto.randomUUID().replace(/-/g, '')
            : Math.random().toString(36).slice(2);

    return `${prefix}-${random}`.slice(0, 64);
}

/**
 * Reload the current page's props, carrying the player token.
 */
export function reloadWithToken(token: string | null, only: string[] = []) {
    router.reload({
        headers: withPlayerToken(token),
        only: only.length ? only : undefined,
    });
}
