import { usePage } from '@inertiajs/vue3';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

declare global {
    interface Window {
        Pusher: typeof Pusher;
        Echo: Echo<any>;
    }
}

let echoInstance: Echo<any> | null = null;

export function useEcho(): Echo<any> | null {
    if (typeof window === 'undefined') {
        return null;
    }

    if (echoInstance) {
        return echoInstance;
    }

    window.Pusher = Pusher;

    const page = usePage();
    const reverbProps = (page.props as Record<string, unknown>).reverb as
        | { key?: string; host?: string; port?: number; scheme?: string }
        | undefined;

    const key = reverbProps?.key || import.meta.env.VITE_REVERB_APP_KEY;
    if (!key) {
        return null;
    }

    const host = reverbProps?.host || import.meta.env.VITE_REVERB_HOST || window.location.hostname;
    const port = Number(reverbProps?.port || import.meta.env.VITE_REVERB_PORT || (window.location.protocol === 'https:' ? 443 : 8080));
    const scheme = reverbProps?.scheme || import.meta.env.VITE_REVERB_SCHEME || (window.location.protocol === 'https:' ? 'https' : 'http');
    const isTls = scheme === 'https';

    try {
        echoInstance = new Echo({
            broadcaster: 'reverb',
            key,
            wsHost: host,
            wsPort: port,
            wssPort: port,
            forceTLS: isTls,
            enabledTransports: ['ws', 'wss'],
            authEndpoint: '/broadcasting/auth',
        });

        window.Echo = echoInstance;
    } catch (err) {
        console.warn('Failed to initialize Laravel Echo:', err);
    }

    return echoInstance;
}
