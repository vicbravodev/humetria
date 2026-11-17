import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { benefitOptions } from '@/lib/compensation/benefit-options';

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('benefitOptions', () => {
    const signal = new AbortController().signal;

    it('asks the server for what was typed and remembers the unit of each benefit it finds', async () => {
        const fetcher = vi.fn().mockResolvedValue({
            ok: true,
            json: () =>
                Promise.resolve([
                    { value: 'b1', label: 'Bono', unit: 'pesos' },
                    { value: 'b2', label: 'Vales' },
                    { nope: true },
                    'text',
                ]),
        });
        vi.stubGlobal('fetch', fetcher);
        const { source, unitOf } = benefitOptions('/benefits');

        const options = await source.load('bo', signal);

        expect(fetcher).toHaveBeenCalledWith('/benefits?q=bo', {
            headers: { Accept: 'application/json' },
            signal,
        });
        expect(options).toEqual([
            { value: 'b1', label: 'Bono' },
            { value: 'b2', label: 'Vales' },
        ]);
        expect(unitOf('b1')).toBe('pesos');
        expect(unitOf('b2')).toBe('');
        expect(unitOf('unknown')).toBe('');
    });

    it('fails when the server does', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: false }));

        await expect(
            benefitOptions('/benefits').source.load('x', signal),
        ).rejects.toThrow('Options request failed');
    });

    it('has no options until something is typed', () => {
        expect(benefitOptions('/benefits').source.initial).toEqual([]);
    });

    it('asks for the first page as soon as it opens, like the other server sources', () => {
        expect(benefitOptions('/benefits').source.minLength).toBe(0);
    });

    it('does not offer again a benefit already added, and still knows the unit of the others', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn().mockResolvedValue({
                ok: true,
                json: () =>
                    Promise.resolve([
                        { value: 'b1', label: 'Bono', unit: 'pesos' },
                        { value: 'b2', label: 'Vales', unit: 'pesos' },
                    ]),
            }),
        );
        const { except, unitOf } = benefitOptions('/benefits');
        const source = except(['b1']);

        expect(await source.load('', signal)).toEqual([
            { value: 'b2', label: 'Vales' },
        ]);
        expect(source.minLength).toBe(0);
        expect(source.initial).toEqual([]);
        expect(unitOf('b2')).toBe('pesos');
    });

    it('gives a body that is not a list as no options', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn().mockResolvedValue({
                ok: true,
                json: () => Promise.resolve({}),
            }),
        );

        expect(
            await benefitOptions('/benefits').source.load('x', signal),
        ).toEqual([]);
    });
});
