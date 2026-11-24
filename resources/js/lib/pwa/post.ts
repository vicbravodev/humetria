import { postInit, retryAfterOf } from '@/lib/pwa/delivery';
import type { Answer } from '@/lib/pwa/run-delivery';

/**
 * A POST of the app without a session: JSON in and out, no cookie, no CSRF token (the delivery and the tickets carry their
 * own credential), cut after `timeoutMs`. `fetch` is looked up when it posts, never kept, so the page may replace it. The
 * answer, or `status` null when nothing came back (no network, cut short).
 */
export async function postJson(
    url: string,
    payload: unknown,
    timeoutMs: number,
): Promise<Answer> {
    try {
        const response = await fetch(url, {
            ...postInit(payload),
            signal: AbortSignal.timeout(timeoutMs),
        });
        const json: unknown = await response.json().catch(() => null);

        return {
            status: response.status,
            json,
            retryAfter: retryAfterOf(response.headers.get('Retry-After')),
        };
    } catch {
        return { status: null, json: null, retryAfter: null };
    }
}
