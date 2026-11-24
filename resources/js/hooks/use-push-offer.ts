import { useEffect, useState } from 'react';
import { xsrfToken } from '@/lib/assistant/xsrf';
import {
    dismissOffer,
    dismissedAt,
    forgetPush,
    markedBefore,
    rememberMark,
    shouldOfferPush,
    subscribeForPush,
} from '@/lib/notifications/push';
import type { SubscribeResult } from '@/lib/notifications/push';
import { readPlatform } from '@/lib/pwa/platform';

const http = () => ({
    fetcher: fetch.bind(window),
    xsrf: xsrfToken(document.cookie),
});

// Reached lazily: a private window or blocked storage throws on `window.localStorage` itself, inside the helpers' own try.
const storage = {
    getItem: (key: string) => window.localStorage.getItem(key),
    setItem: (key: string, value: string) =>
        window.localStorage.setItem(key, value),
};

function pushSupported(): boolean {
    return (
        'serviceWorker' in navigator &&
        'PushManager' in window &&
        'Notification' in window
    );
}

/** Subscribes this phone through the worker of the company (none, as in development: nothing to subscribe). */
async function subscribeHere(slug: string): Promise<SubscribeResult> {
    const registration = await navigator.serviceWorker.getRegistration(
        `/me/${slug}`,
    );

    return registration === undefined
        ? 'unavailable'
        : subscribeForPush(slug, registration, http());
}

/** «Salir» and «¿No es usted?»: this phone stops getting the notices of the person who leaves. Best effort, it never fails. */
export function forgetPushHere(slug: string): Promise<void> {
    return forgetPush(slug, {
        container:
            'serviceWorker' in navigator ? navigator.serviceWorker : undefined,
        ...http(),
    });
}

/** A mark was accepted in this browser: from now on the start may offer the notices. */
export function rememberMarkHere(): void {
    rememberMark(storage);
}

/** Whether the offer shows when the start opens: read once, at the first render (the start is behind the session, never rendered by Node). */
function startingState(): 'hidden' | 'offer' {
    if (!pushSupported()) {
        return 'hidden';
    }

    const platform = readPlatform(window);

    return shouldOfferPush({
        supported: true,
        permission: Notification.permission,
        standalone: platform.installed,
        ios: platform.phone === 'iphone',
        markedOnce: markedBefore(storage),
        dismissedAt: dismissedAt(storage),
        now: Date.now(),
    })
        ? 'offer'
        : 'hidden';
}

/**
 * The offer to allow the notices, in the person's start. It shows once a mark was accepted in this browser and the system has not
 * been asked (`shouldOfferPush`); with the permission already given it subscribes by itself, silently, so a phone that someone
 * else used (or one the person left) comes back to this person. `accept` asks the system and subscribes; `later` («Ahora no»)
 * hides it for 14 days.
 */
export function usePushOffer(slug: string) {
    const [state, setState] = useState<'hidden' | 'offer' | 'working'>(
        startingState,
    );

    useEffect(() => {
        // Already allowed: subscribe by itself, so this phone belongs to whoever opened their start.
        if (pushSupported() && Notification.permission === 'granted') {
            void subscribeHere(slug);
        }
    }, [slug]);

    return {
        visible: state !== 'hidden',
        working: state === 'working',
        accept: async () => {
            setState('working');

            if ((await Notification.requestPermission()) === 'granted') {
                await subscribeHere(slug);
            }

            setState('hidden');
        },
        later: () => {
            dismissOffer(storage, Date.now());
            setState('hidden');
        },
    };
}
