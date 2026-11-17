import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { fetchKeepingRequestId } from '@/lib/assistant/transport';

describe('fetchKeepingRequestId', () => {
    afterEach(() => vi.unstubAllGlobals());

    it('hands back the id of each response and the response itself', async () => {
        const response = new Response('{}', {
            headers: { 'X-Request-Id': 'b3f1c2d4' },
        });
        const fetch = vi.fn(() => Promise.resolve(response));
        const seen: (string | null)[] = [];
        vi.stubGlobal('fetch', fetch);

        const answered = await fetchKeepingRequestId((id) => seen.push(id))(
            '/admin/acme/assistant/chat',
            { method: 'POST' },
        );

        expect(answered).toBe(response);
        expect(fetch).toHaveBeenCalledWith('/admin/acme/assistant/chat', {
            method: 'POST',
        });
        expect(seen).toEqual(['b3f1c2d4']);
    });

    it('hands back null when the response has no id', async () => {
        const seen: (string | null)[] = [];
        vi.stubGlobal('fetch', () => Promise.resolve(new Response('')));

        await fetchKeepingRequestId((id) => seen.push(id))('/x');

        expect(seen).toEqual([null]);
    });
});
