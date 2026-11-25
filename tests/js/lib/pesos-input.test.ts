import { describe, expect, it } from 'vite-plus/test';
import { formatPesos, rawPesos } from '@/lib/pesos-input';

describe('rawPesos', () => {
    it('leaves the number the server reads: no sign, no spaces, no thousands commas', () => {
        expect(rawPesos('15,000.50')).toBe('15000.50');
        expect(rawPesos(' $ 1,234,567 ')).toBe('1234567');
        expect(rawPesos('15000')).toBe('15000');
        expect(rawPesos('')).toBe('');
    });
});

describe('formatPesos', () => {
    it('writes an amount with thousands commas and cents, the way pesos are written', () => {
        expect(formatPesos('15000')).toBe('15,000.00');
        expect(formatPesos('15000.5')).toBe('15,000.50');
        expect(formatPesos('1234567.89')).toBe('1,234,567.89');
        expect(formatPesos('999')).toBe('999.00');
        expect(formatPesos('0.5')).toBe('0.50');
    });

    it('says nothing of what is not an amount of pesos', () => {
        expect(formatPesos('')).toBeNull();
        expect(formatPesos('abc')).toBeNull();
        expect(formatPesos('15000.555')).toBeNull();
        expect(formatPesos('1.2.3')).toBeNull();
        expect(formatPesos('-5')).toBeNull();
    });
});
