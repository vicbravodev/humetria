import { describe, expect, it } from 'vite-plus/test';

import {
    demosLine,
    pesos,
    pesosAndCents,
    quietLine,
    whatsAppLine,
} from '@/lib/overview';

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

describe('pesosAndCents', () => {
    it('keeps the cents, for the small amounts of WhatsApp', () => {
        expect(pesosAndCents(68)).toContain('0.68');
        expect(pesosAndCents(1_234_567)).toContain('12,345.67');
    });
});

describe('whatsAppLine', () => {
    it('says what Meta charged, what was charged to the companies and the margin', () => {
        const line = whatsAppLine({
            messages: 4,
            costCents: 68,
            chargedCents: 88,
            marginCents: 20,
        });

        expect(line).toContain('Costo de Meta $0.68');
        expect(line).toContain('cobrado $0.88');
        expect(line).toContain('margen $0.20');
    });

    it('says nothing went out when there are no messages', () => {
        expect(
            whatsAppLine({
                messages: 0,
                costCents: 0,
                chargedCents: 0,
                marginCents: 0,
            }),
        ).toBe('Todavía no sale ningún mensaje este mes.');
    });
});
