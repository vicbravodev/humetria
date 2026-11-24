import { vi } from 'vitest';
import type { PushRegistration } from '@/lib/notifications/push';

/** The 65 bytes of a VAPID public key, and the same in base64url as the server gives them. */
export const KEY_BYTES = Uint8Array.from({ length: 65 }, (_, index) => index);
export const KEY = btoa(String.fromCharCode(...KEY_BYTES))
    .replaceAll('+', '-')
    .replaceAll('/', '_')
    .replaceAll('=', '');

/** A subscription of the browser, made with `key` (none: one that was made with no key at all). */
export function subscription(
    endpoint = 'https://fcm.googleapis.com/fcm/send/abc',
    key: Uint8Array | null = KEY_BYTES,
) {
    return {
        endpoint,
        options: { applicationServerKey: key === null ? null : key.buffer },
        toJSON: () => ({ endpoint, keys: { p256dh: 'p', auth: 'a' } }),
        unsubscribe: vi.fn<() => Promise<boolean>>().mockResolvedValue(true),
    };
}

/** The push manager of a worker's registration, with the subscription it already has (none by default). */
export function registration(
    existing: ReturnType<typeof subscription> | null = null,
) {
    const pushManager = {
        getSubscription: vi.fn().mockResolvedValue(existing),
        subscribe: vi.fn().mockResolvedValue(subscription()),
    };
    const phone: PushRegistration = { pushManager };

    return { ...phone, pushManager };
}

export const json = (body: unknown, status = 200) =>
    new Response(JSON.stringify(body), { status });
export const nothing = (status: number) => new Response(null, { status });

/** A server that answers each request, in order, with the next answer. */
export function server(...answers: Response[]) {
    const fetcher = vi.fn<typeof fetch>();
    answers.forEach((answer) => fetcher.mockResolvedValueOnce(answer));

    return fetcher;
}
