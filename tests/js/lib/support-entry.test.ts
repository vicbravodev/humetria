import { describe, expect, it } from 'vite-plus/test';
import { timeLeftText } from '@/lib/support-entry';

describe('timeLeftText', () => {
    it('says the whole minutes left, a started minute counting as one', () => {
        expect(timeLeftText(26 * 60)).toBe('Quedan 26 min');
        expect(timeLeftText(25 * 60 + 1)).toBe('Quedan 26 min');
        expect(timeLeftText(61)).toBe('Quedan 2 min');
    });

    it('says the last minute in singular', () => {
        expect(timeLeftText(60)).toBe('Queda 1 min');
        expect(timeLeftText(1)).toBe('Queda 1 min');
    });

    it('says it is over at zero', () => {
        expect(timeLeftText(0)).toBe('Tiempo terminado');
    });
});
