import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import type {
    PrivateGameState,
    PublicGameState,
    ReverbConfig,
} from '@/types/game';

type PublicEventName =
    | 'player.joined'
    | 'player.left'
    | 'player.ready'
    | 'game.settings-updated'
    | 'game.started'
    | 'card.played'
    | 'pile.collected'
    | 'turn.changed'
    | 'player.disconnected'
    | 'player.reconnected'
    | 'player.finished'
    | 'game.completed'
    | 'game.cancelled';

interface PublicEventPayload {
    state: PublicGameState;
    payload: Record<string, unknown>;
}

interface PrivateEventPayload {
    state: PrivateGameState;
}

interface Options {
    gameId: string;
    playerId: string;
    token: string | null;
    reverb: ReverbConfig;
    onPublic: (event: PublicEventName, payload: PublicEventPayload) => void;
    onPrivate?: (payload: PrivateEventPayload) => void;
}

const PUBLIC_EVENTS: PublicEventName[] = [
    'player.joined',
    'player.left',
    'player.ready',
    'game.settings-updated',
    'game.started',
    'card.played',
    'pile.collected',
    'turn.changed',
    'player.disconnected',
    'player.reconnected',
    'player.finished',
    'game.completed',
    'game.cancelled',
];

/**
 * Subscribes to the two private channels for a game.
 *
 * Echo is created per page rather than globally because the auth request has to
 * carry this player's token — that is what stops anyone subscribing to a hand that
 * is not theirs.
 */
export function useGameChannel(options: Options) {
    const connected = ref(false);
    let echo: Echo<'reverb'> | null = null;

    const csrfToken = () =>
        document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.content ?? '';

    onMounted(() => {
        if (!options.token || !options.reverb?.key) {
            return;
        }

        window.Pusher = Pusher;

        echo = new Echo({
            broadcaster: 'reverb',
            key: options.reverb.key,
            wsHost: options.reverb.host,
            wsPort: options.reverb.port,
            wssPort: options.reverb.port,
            forceTLS: options.reverb.scheme === 'https',
            enabledTransports: ['ws', 'wss'],
            auth: {
                headers: {
                    'X-Player-Token': options.token,
                    'X-CSRF-TOKEN': csrfToken(),
                },
            },
        });

        const connection = (echo.connector as { pusher?: Pusher }).pusher
            ?.connection;

        connection?.bind('connected', () => (connected.value = true));
        connection?.bind('disconnected', () => (connected.value = false));
        connection?.bind('unavailable', () => (connected.value = false));

        const table = echo.private(`game.${options.gameId}`);

        PUBLIC_EVENTS.forEach((event) => {
            table.listen(`.${event}`, (payload: PublicEventPayload) =>
                options.onPublic(event, payload),
            );
        });

        if (options.onPrivate && options.playerId) {
            echo.private(
                `game.${options.gameId}.player.${options.playerId}`,
            ).listen('.hand.updated', (payload: PrivateEventPayload) =>
                options.onPrivate?.(payload),
            );
        }
    });

    onBeforeUnmount(() => {
        if (!echo) {
            return;
        }

        echo.leave(`game.${options.gameId}`);

        if (options.playerId) {
            echo.leave(`game.${options.gameId}.player.${options.playerId}`);
        }

        echo.disconnect();
        echo = null;
    });

    return { connected };
}
