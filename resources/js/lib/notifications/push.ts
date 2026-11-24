import { NETWORK_TIMEOUT_MS } from '@/lib/pwa/sw-rules';
import type { HttpDeps } from '@/lib/worker-access/http';
import { destroy, show, store } from '@/routes/portal/push';

/** How many days «Ahora no» hides the offer. */
export const DISMISS_DAYS = 14;

const DAY_MS = 86_400_000;
const MARKED = 'humetria-push-marked';
const DISMISSED = 'humetria-push-dismissed';

/** What decides whether the page offers the notices. */
export type PushOfferState = {
    /** The browser has a service worker, push and notifications. */
    supported: boolean;
    permission: NotificationPermission;
    /** The app runs installed (its own window). */
    standalone: boolean;
    ios: boolean;
    /** The person made a mark that was accepted, in this browser. */
    markedOnce: boolean;
    dismissedAt: number | null;
    now: number;
};

/**
 * Whether to ask the person to allow the notices: only after a first mark that was accepted, only when the system has not been
 * asked yet (an allowed browser subscribes by itself, a refused one is never asked again), on an iPhone only with the app
 * installed (it cannot push otherwise), and not for 14 days after «Ahora no».
 */
export function shouldOfferPush(state: PushOfferState): boolean {
    const quiet =
        state.dismissedAt !== null &&
        state.now - state.dismissedAt < DISMISS_DAYS * DAY_MS;

    return (
        state.supported &&
        state.permission === 'default' &&
        (!state.ios || state.standalone) &&
        state.markedOnce &&
        !quiet
    );
}

/** What the server takes of `subscription.toJSON()`. */
export type SubscriptionBody = {
    endpoint: string;
    keys: { p256dh: string; auth: string };
};

/** The address (https) and the two keys of a browser's subscription, or null when it lacks any: the server would refuse it. */
export function subscriptionBody(
    json: PushSubscriptionJSON,
): SubscriptionBody | null {
    const { endpoint } = json;
    const p256dh = json.keys?.['p256dh'];
    const auth = json.keys?.['auth'];

    return endpoint?.startsWith('https://') === true &&
        typeof p256dh === 'string' &&
        p256dh !== '' &&
        typeof auth === 'string' &&
        auth !== ''
        ? { endpoint, keys: { p256dh, auth } }
        : null;
}

/** The VAPID public key the server gives in base64url, as the bytes the browser subscribes with. */
export function publicKeyBytes(key: string): Uint8Array<ArrayBuffer> {
    const raw = atob(key.replaceAll('-', '+').replaceAll('_', '/'));

    return Uint8Array.from(raw, (char) => char.codePointAt(0) ?? 0);
}

/** The part of `localStorage` the offer uses. Each access may fail (a private window, blocked storage): the page goes on without it. */
type Store = Pick<Storage, 'getItem' | 'setItem'>;

export function markedBefore(storage: Store): boolean {
    try {
        return storage.getItem(MARKED) === '1';
    } catch {
        return false;
    }
}

/** A mark was accepted in this browser: the offer may come. Nothing of the person is kept, only that. */
export function rememberMark(storage: Store): void {
    try {
        storage.setItem(MARKED, '1');
    } catch {
        // Without storage the offer simply does not come.
    }
}

export function dismissOffer(storage: Store, now: number): void {
    try {
        storage.setItem(DISMISSED, String(now));
    } catch {
        // It comes back sooner than 14 days: nothing to remember it with.
    }
}

export function dismissedAt(storage: Store): number | null {
    try {
        const raw = storage.getItem(DISMISSED);
        const at = raw === null ? Number.NaN : Number(raw);

        return Number.isFinite(at) ? at : null;
    } catch {
        return null;
    }
}

/** What the page uses of a browser's push subscription. */
export type PushSubscriptionLike = {
    endpoint: string;
    options: { applicationServerKey: ArrayBuffer | null };
    toJSON(): PushSubscriptionJSON;
    unsubscribe(): Promise<boolean>;
};

/** What the page uses of the worker's registration. */
export type PushRegistration = {
    pushManager: {
        getSubscription(): Promise<PushSubscriptionLike | null>;
        subscribe(options: {
            userVisibleOnly: boolean;
            applicationServerKey: Uint8Array<ArrayBuffer>;
        }): Promise<PushSubscriptionLike>;
    };
};

export type SubscribeResult = 'subscribed' | 'unavailable' | 'failed';

/** A JSON request of the page's own origin, with the person's session and CSRF token; it never waits longer than the worker does. */
function send(
    {
        method,
        url,
        body,
    }: { method: 'GET' | 'POST' | 'DELETE'; url: string; body?: unknown },
    { fetcher, xsrf }: HttpDeps,
): Promise<Response> {
    return fetcher(url, {
        method,
        credentials: 'same-origin',
        signal: AbortSignal.timeout(NETWORK_TIMEOUT_MS),
        headers: {
            Accept: 'application/json',
            ...(body === undefined
                ? {}
                : { 'Content-Type': 'application/json' }),
            ...(xsrf === null ? {} : { 'X-XSRF-TOKEN': xsrf }),
        },
        ...(body === undefined ? {} : { body: JSON.stringify(body) }),
    });
}

/** The key the phone subscribes with; null when the push is off on the server. */
async function publicKeyOf(
    slug: string,
    deps: HttpDeps,
): Promise<string | null> {
    const response = await send({ method: 'GET', url: show.url(slug) }, deps);

    if (!response.ok) {
        throw new Error('The key could not be asked.');
    }

    const json: unknown = await response.json();

    if (typeof json === 'object' && json !== null && 'publicKey' in json) {
        if (typeof json.publicKey === 'string' || json.publicKey === null) {
            return json.publicKey;
        }
    }

    throw new Error('The answer has no key.');
}

function sameBytes(saved: ArrayBuffer | null, key: Uint8Array): boolean {
    if (saved === null) {
        return false;
    }

    const bytes = new Uint8Array(saved);

    return (
        bytes.length === key.length &&
        bytes.every((byte, index) => byte === key[index])
    );
}

/** The browser's subscription with this key: the one it has, or a new one (one made with another key cannot be reused). */
async function subscriptionFor(
    registration: PushRegistration,
    key: Uint8Array<ArrayBuffer>,
): Promise<PushSubscriptionLike> {
    const existing = await registration.pushManager.getSubscription();

    if (
        existing !== null &&
        sameBytes(existing.options.applicationServerKey, key)
    ) {
        return existing;
    }

    await existing?.unsubscribe();

    return registration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: key,
    });
}

/**
 * Subscribes this phone to the notices of the company and tells the server (the person is the session's). It is what runs when
 * the person allows them and, silently, every time they open their start with the permission already given: an
 * address that moved to another person's session (a shared phone) comes back to this one. `unavailable`: the server has no keys.
 */
export async function subscribeForPush(
    slug: string,
    registration: PushRegistration,
    deps: HttpDeps,
): Promise<SubscribeResult> {
    try {
        const key = await publicKeyOf(slug, deps);

        if (key === null) {
            return 'unavailable';
        }

        const body = subscriptionBody(
            (await subscriptionFor(registration, publicKeyBytes(key))).toJSON(),
        );

        if (body === null) {
            return 'failed';
        }

        return (
            await send({ method: 'POST', url: store.url(slug), body }, deps)
        ).ok
            ? 'subscribed'
            : 'failed';
    } catch {
        return 'failed';
    }
}

/** What the page reaches the company's worker through (`navigator.serviceWorker`; none where the browser has no worker). */
export type PushContainer = {
    getRegistration(scope: string): Promise<PushRegistration | undefined>;
};

/**
 * Gives this phone's subscription up: tells the server to forget it and drops it in the browser. «Salir» and «¿No es usted?» call it
 * before they close the session. Best effort and it never fails: without network the server keeps the subscription until its
 * push service says it is gone, and a notice to it carries the same fixed text, no data.
 */
export async function forgetPush(
    slug: string,
    { container, ...deps }: HttpDeps & { container: PushContainer | undefined },
): Promise<void> {
    try {
        const registration = await container?.getRegistration(`/me/${slug}`);
        const subscription = await registration?.pushManager.getSubscription();

        if (subscription === undefined || subscription === null) {
            return;
        }

        try {
            await send(
                {
                    method: 'DELETE',
                    url: destroy.url(slug),
                    body: { endpoint: subscription.endpoint },
                },
                deps,
            );
        } catch {
            // The subscription still goes from this phone.
        }

        await subscription.unsubscribe();
    } catch {
        // Leaving never waits on this.
    }
}
