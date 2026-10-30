import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { loadServerOptions } from '@/lib/server-options';

const respond = (body: unknown, ok = true) =>
    vi.fn().mockResolvedValue({ ok, json: () => Promise.resolve(body) });

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('loadServerOptions', () => {
    it('asks the server for what was typed and keeps the options it answers', async () => {
        const fetch = respond([
            { value: 'a1', label: 'Ventas' },
            { value: 7, label: 'not an option' },
            'nothing',
        ]);
        vi.stubGlobal('fetch', fetch);
        const signal = new AbortController().signal;

        await expect(
            loadServerOptions('/admin/acme/areas/options', 'ven tas', signal),
        ).resolves.toEqual([{ value: 'a1', label: 'Ventas' }]);
        expect(fetch).toHaveBeenCalledWith(
            '/admin/acme/areas/options?q=ven+tas',
            { headers: { Accept: 'application/json' }, signal },
        );
    });

    it('fails when the server does, and reads anything but a list as none', async () => {
        const signal = new AbortController().signal;
        vi.stubGlobal('fetch', respond([], false));
        await expect(loadServerOptions('/x', 'a', signal)).rejects.toThrow();

        vi.stubGlobal('fetch', respond({ value: 'a1', label: 'Ventas' }));
        await expect(loadServerOptions('/x', 'a', signal)).resolves.toEqual([]);
    });
});
