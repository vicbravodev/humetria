import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { loadServerOptions, serverSource } from '@/lib/server-options';

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

describe('serverSource', () => {
    it('asks the server from the first letter, showing the given options until then', async () => {
        vi.stubGlobal('fetch', respond([{ value: 'a1', label: 'Ventas' }]));
        const initial = [{ value: '', label: 'Todas' }];
        const source = serverSource('/x', initial);

        expect(source.initial).toBe(initial);
        expect(source.minLength).toBe(1);
        expect(source.allowNew).toBe(false);
        await expect(
            source.load('v', new AbortController().signal),
        ).resolves.toEqual([{ value: 'a1', label: 'Ventas' }]);
        expect(serverSource('/x', [], { allowNew: true }).allowNew).toBe(true);
    });

    it("can use each option's name as its value", async () => {
        vi.stubGlobal('fetch', respond([{ value: 'a1', label: 'Ventas' }]));

        await expect(
            serverSource('/x', [], { byName: true }).load(
                'v',
                new AbortController().signal,
            ),
        ).resolves.toEqual([{ value: 'Ventas', label: 'Ventas' }]);
    });
});
