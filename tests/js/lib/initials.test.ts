import { describe, expect, it } from 'vite-plus/test';
import { initials } from '@/lib/initials';

describe('initials', () => {
    it('takes the first and last name', () => {
        expect(initials('María de la Luz Pérez')).toBe('MP');
    });

    it('takes one letter from a single name', () => {
        expect(initials('ana')).toBe('A');
    });

    it('keeps an accented letter whole', () => {
        expect(initials('Óscar Ñúñez')).toBe('ÓÑ');
    });

    it('is empty for a blank name', () => {
        expect(initials('   ')).toBe('');
    });
});
