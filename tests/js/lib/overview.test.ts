import { describe, expect, it } from 'vite-plus/test';

import { demosLine, pesos, quietLine } from '@/lib/overview';

describe('pesos', () => {
    it('writes cents as whole pesos', () => {
        expect(pesos(2_490_000)).toContain('24,900');
        expect(pesos(0)).toContain('0');
    });
});

describe('demosLine', () => {
    it('says how many wait, in the right number', () => {
        expect(demosLine(0)).toBe('Ninguna espera respuesta.');
        expect(demosLine(1)).toBe('1 espera respuesta.');
        expect(demosLine(3)).toBe('3 esperan respuesta.');
    });
});

describe('quietLine', () => {
    it('says how many companies have not entered, in the right number', () => {
        expect(quietLine(0)).toContain('Todas');
        expect(quietLine(1)).toContain('1 empresa no tiene');
        expect(quietLine(4)).toContain('4 empresas no tienen');
    });
});
