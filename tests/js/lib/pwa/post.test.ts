import { afterEach, describe, expect, it, vi } from 'vitest';
import { postJson } from '@/lib/pwa/post';

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('postJson', () => {
    it('sends JSON without cookies, with a time limit, and reads status, body and Retry-After', async () => {
        const fetcher = vi.fn<typeof fetch>(() =>
            Promise.resolve(
                new Response('{"ok":true}', {
                    status: 429,
                    headers: { 'Retry-After': '120' },
                }),
            ),
        );
        vi.stubGlobal('fetch', fetcher);

        const answer = await postJson('/me/acme/tickets', { a: 1 }, 5_000);

        expect(answer).toEqual({
            status: 429,
            json: { ok: true },
            retryAfter: 120,
        });

        const [url, init] = fetcher.mock.calls[0] ?? [];

        expect(url).toBe('/me/acme/tickets');
        expect(init).toMatchObject({
            method: 'POST',
            credentials: 'omit',
            body: '{"a":1}',
        });
        expect(init?.signal).toBeInstanceOf(AbortSignal);
    });

    it('gives no body when the answer is not JSON', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(() =>
                Promise.resolve(new Response('<html>', { status: 502 })),
            ),
        );

        expect(await postJson('/x', {}, 1_000)).toEqual({
            status: 502,
            json: null,
            retryAfter: null,
        });
    });

    it('says there was no answer when the network fails or times out', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(() => Promise.reject(new TypeError('Failed to fetch'))),
        );

        expect(await postJson('/x', {}, 1_000)).toEqual({
            status: null,
            json: null,
            retryAfter: null,
        });
    });

    it('looks fetch up when it posts, so the page can replace it afterwards', async () => {
        const first = vi.fn(() => Promise.reject(new TypeError('down')));
        vi.stubGlobal('fetch', first);
        await postJson('/x', {}, 1_000);

        const second = vi.fn(() => Promise.resolve(new Response('{}')));
        vi.stubGlobal('fetch', second);
        await postJson('/x', {}, 1_000);

        expect(first).toHaveBeenCalledTimes(1);
        expect(second).toHaveBeenCalledTimes(1);
    });
});
