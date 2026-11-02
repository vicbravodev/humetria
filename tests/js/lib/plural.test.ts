import { describe, expect, it } from 'vite-plus/test';
import { countOf, plural } from '@/lib/plural';

describe('plural', () => {
    it('takes the singular only for exactly one', () => {
        expect(plural(1, 'falta', 'faltan')).toBe('falta');
        expect(plural(0, 'falta', 'faltan')).toBe('faltan');
        expect(plural(2, 'falta', 'faltan')).toBe('faltan');
    });
});

describe('countOf', () => {
    it('writes the count with its noun in the right number', () => {
        expect(countOf(1, 'persona', 'personas')).toBe('1 persona');
        expect(countOf(0, 'persona', 'personas')).toBe('0 personas');
        expect(countOf(12, 'persona', 'personas')).toBe('12 personas');
    });
});
