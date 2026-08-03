import { onMounted, ref } from 'vue';

export type SoundName =
    'join' | 'play' | 'collect' | 'turn' | 'warning' | 'win';

const STORAGE_KEY = 'follow-suit.muted';

/**
 * Small synthesised sound effects.
 *
 * Tones are generated with the Web Audio API rather than shipped as files, so there
 * is nothing extra to download and nothing to fail on a slow connection.
 */
const TONES: Record<
    SoundName,
    { frequency: number; duration: number; type: OscillatorType }[]
> = {
    join: [
        { frequency: 523.25, duration: 0.09, type: 'sine' },
        { frequency: 783.99, duration: 0.12, type: 'sine' },
    ],
    play: [{ frequency: 320, duration: 0.06, type: 'triangle' }],
    collect: [
        { frequency: 220, duration: 0.08, type: 'sawtooth' },
        { frequency: 165, duration: 0.14, type: 'sawtooth' },
    ],
    turn: [{ frequency: 660, duration: 0.1, type: 'sine' }],
    warning: [
        { frequency: 880, duration: 0.08, type: 'square' },
        { frequency: 880, duration: 0.08, type: 'square' },
    ],
    win: [
        { frequency: 523.25, duration: 0.12, type: 'sine' },
        { frequency: 659.25, duration: 0.12, type: 'sine' },
        { frequency: 783.99, duration: 0.24, type: 'sine' },
    ],
};

const muted = ref(false);
let context: AudioContext | null = null;

export function useSound() {
    onMounted(() => {
        try {
            muted.value = window.localStorage.getItem(STORAGE_KEY) === '1';
        } catch {
            muted.value = false;
        }
    });

    const toggleMute = () => {
        muted.value = !muted.value;

        try {
            window.localStorage.setItem(STORAGE_KEY, muted.value ? '1' : '0');
        } catch {
            // Storage is unavailable; the setting just will not persist.
        }
    };

    const play = (name: SoundName) => {
        if (muted.value || typeof window === 'undefined') {
            return;
        }

        try {
            const AudioContextClass =
                window.AudioContext ??
                (
                    window as unknown as {
                        webkitAudioContext: typeof AudioContext;
                    }
                ).webkitAudioContext;

            context ??= new AudioContextClass();

            if (context.state === 'suspended') {
                void context.resume();
            }

            let startAt = context.currentTime;

            TONES[name].forEach((tone) => {
                const oscillator = context!.createOscillator();
                const gain = context!.createGain();

                oscillator.type = tone.type;
                oscillator.frequency.value = tone.frequency;

                gain.gain.setValueAtTime(0.0001, startAt);
                gain.gain.exponentialRampToValueAtTime(0.12, startAt + 0.01);
                gain.gain.exponentialRampToValueAtTime(
                    0.0001,
                    startAt + tone.duration,
                );

                oscillator.connect(gain).connect(context!.destination);
                oscillator.start(startAt);
                oscillator.stop(startAt + tone.duration + 0.02);

                startAt += tone.duration;
            });
        } catch {
            // Audio is not available in this context.
        }
    };

    return { muted, toggleMute, play };
}
