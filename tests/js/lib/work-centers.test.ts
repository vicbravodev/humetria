import { describe, expect, it } from 'vite-plus/test';

import { guidesText, staffText } from '@/lib/work-centers';

describe('staffText', () => {
    it('counts the people of a work center', () => {
        expect(staffText(0)).toBe('Sin personal');
        expect(staffText(1)).toBe('1 persona');
        expect(staffText(48)).toBe('48 personas');
    });
});

describe('guidesText', () => {
    it('names the NOM-035 guides that apply', () => {
        expect(guidesText([])).toBeNull();
        expect(guidesText(['I'])).toBe('NOM-035: Guía I');
        expect(guidesText(['I', 'II'])).toBe('NOM-035: Guías I y II');
        expect(guidesText(['I', 'III'])).toBe('NOM-035: Guías I y III');
    });
});
