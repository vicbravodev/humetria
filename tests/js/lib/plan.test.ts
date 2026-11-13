import { describe, expect, it } from 'vite-plus/test';

import { toolState, useLine } from '@/lib/plan';

const noun = { one: 'lugar', many: 'lugares' };

describe('useLine', () => {
    it('says nothing limits a company with no limit', () => {
        expect(useLine({ used: 3, limit: null }, noun)).toBe(
            'Sin tope: usas 3 lugares.',
        );
        expect(useLine({ used: 1, limit: null }, noun)).toBe(
            'Sin tope: usas 1 lugar.',
        );
        expect(useLine({ used: 0, limit: null }, noun)).toBe(
            'Sin tope: todavía no usas ninguno.',
        );
    });

    it('says how many are left, in the right number', () => {
        expect(useLine({ used: 2, limit: 10 }, noun)).toBe('Quedan 8 lugares.');
        expect(useLine({ used: 9, limit: 10 }, noun)).toBe('Queda 1 lugar.');
    });

    it('says it is full at the limit, and what to do above it', () => {
        expect(useLine({ used: 10, limit: 10 }, noun)).toContain(
            'Ya usas todo',
        );
        expect(useLine({ used: 12, limit: 10 }, noun)).toContain(
            'Tienes 2 de más',
        );
    });
});

describe('toolState', () => {
    it('says on, on until a day, or off', () => {
        expect(toolState({ label: 'x', active: true, endsOn: null })).toBe(
            'Incluida',
        );
        expect(
            toolState({ label: 'x', active: true, endsOn: '1 dic 2026' }),
        ).toBe('Incluida hasta el 1 dic 2026');
        expect(toolState({ label: 'x', active: false, endsOn: null })).toBe(
            'No incluida',
        );
    });
});
