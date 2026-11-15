import { describe, expect, it, vi } from 'vitest';
import { trackLead } from '@/lib/analytics';

const ID = '9d3f6a52-8a7c-4d49-9d0e-5f3a1b2c4d5e';

function memoryStorage(initial: Record<string, string> = {}) {
    const values = new Map(Object.entries(initial));

    return {
        getItem: (key: string) => values.get(key) ?? null,
        setItem: (key: string, value: string) => {
            values.set(key, value);
        },
        values,
    };
}

describe('trackLead', () => {
    it('reports generate_lead once, with no parameters, and remembers the id', () => {
        const gtag = vi.fn();
        const storage = memoryStorage();

        trackLead({ gtag }, storage, ID);

        expect(gtag).toHaveBeenCalledTimes(1);
        expect(gtag).toHaveBeenCalledWith('event', 'generate_lead');
        expect([...storage.values.values()]).toEqual([ID]);
    });

    it('does nothing when Google was never loaded, and remembers nothing', () => {
        const storage = memoryStorage();

        trackLead({}, storage, ID);

        expect(storage.values.size).toBe(0);
    });

    it.each([undefined, ''])('does nothing without an id (%s)', (id) => {
        const gtag = vi.fn();
        const storage = memoryStorage();

        trackLead({ gtag }, storage, id);

        expect(gtag).not.toHaveBeenCalled();
        expect(storage.values.size).toBe(0);
    });

    it('does not report the same request twice', () => {
        const gtag = vi.fn();
        const storage = memoryStorage();

        trackLead({ gtag }, storage, ID);
        trackLead({ gtag }, storage, ID);

        expect(gtag).toHaveBeenCalledTimes(1);
    });

    it('reports a different request after another one', () => {
        const gtag = vi.fn();
        const storage = memoryStorage();

        trackLead({ gtag }, storage, ID);
        trackLead({ gtag }, storage, '11111111-2222-4333-8444-555555555555');

        expect(gtag).toHaveBeenCalledTimes(2);
    });
});
