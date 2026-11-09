import { describe, expect, it } from 'vitest';
import { phaseRows } from '@/lib/attendance/punch-phase';

describe('phaseRows', () => {
    it('shows each test in the order it happens, the one in course and what is left', () => {
        expect(phaseRows('locating')).toEqual([
            { label: 'Leyendo su ubicación', state: 'current' },
            {
                label: 'Confirme con su huella, su rostro o su PIN',
                state: 'pending',
            },
            { label: 'Comprobando el código y la zona', state: 'pending' },
        ]);
        expect(phaseRows('signing').map(({ state }) => state)).toEqual([
            'done',
            'current',
            'pending',
        ]);
        expect(phaseRows('verifying').map(({ state }) => state)).toEqual([
            'done',
            'done',
            'current',
        ]);
    });
});
