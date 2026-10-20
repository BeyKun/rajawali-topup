/**
 * Play a gentle, high-quality synthesized chime for incoming notifications
 * using the Web Audio API (no external asset downloads required).
 */
export function playNotificationSound(): void {
    if (typeof window === 'undefined') {
        return;
    }

    try {
        const AudioContextClass = window.AudioContext || (window as unknown as { webkitAudioContext: typeof AudioContext }).webkitAudioContext;
        if (!AudioContextClass) {
            return;
        }

        const ctx = new AudioContextClass();

        // Resume if suspended (browser autoplay policy)
        if (ctx.state === 'suspended') {
            void ctx.resume();
        }

        const now = ctx.currentTime;

        // Tone 1: E6 (1318.51 Hz)
        const osc1 = ctx.createOscillator();
        const gain1 = ctx.createGain();
        osc1.type = 'sine';
        osc1.frequency.setValueAtTime(1318.51, now);
        gain1.gain.setValueAtTime(0, now);
        gain1.gain.linearRampToValueAtTime(0.2, now + 0.03);
        gain1.gain.exponentialRampToValueAtTime(0.001, now + 0.35);
        osc1.connect(gain1);
        gain1.connect(ctx.destination);
        osc1.start(now);
        osc1.stop(now + 0.35);

        // Tone 2: B6 (1975.53 Hz) - pleasant major chime
        const osc2 = ctx.createOscillator();
        const gain2 = ctx.createGain();
        osc2.type = 'sine';
        osc2.frequency.setValueAtTime(1975.53, now + 0.12);
        gain2.gain.setValueAtTime(0, now + 0.12);
        gain2.gain.linearRampToValueAtTime(0.25, now + 0.15);
        gain2.gain.exponentialRampToValueAtTime(0.001, now + 0.6);
        osc2.connect(gain2);
        gain2.connect(ctx.destination);
        osc2.start(now + 0.12);
        osc2.stop(now + 0.6);
    } catch {
        // Silently fallback if audio context isn't allowed or is blocked
    }
}
