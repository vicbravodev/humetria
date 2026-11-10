import { describe, expect, it } from 'vite-plus/test';
import { limitLine } from '@/lib/limits';

describe('limitLine', () => {
    it('says how many places are left', () => {
        expect(limitLine({ used: 42, amount: 50, remaining: 8 })).toBe(
            'Quedan 8 lugares.',
        );
        expect(limitLine({ used: 49, amount: 50, remaining: 1 })).toBe(
            'Queda 1 lugar.',
        );
    });

    it('says there is no room at the limit', () => {
        expect(limitLine({ used: 50, amount: 50, remaining: 0 })).toBe(
            'Sin lugares: no puede agregar más hasta ampliar el plan.',
        );
    });

    it('says how many it has over when above the limit', () => {
        expect(limitLine({ used: 53, amount: 50, remaining: 0 })).toBe(
            'Tiene 3 lugares de más: conserva lo que tiene y no puede agregar.',
        );
        expect(limitLine({ used: 51, amount: 50, remaining: 0 })).toBe(
            'Tiene 1 lugar de más: conserva lo que tiene y no puede agregar.',
        );
    });
});
