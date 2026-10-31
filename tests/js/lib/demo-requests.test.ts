import { describe, expect, it } from 'vite-plus/test';

import { toolsText } from '@/lib/demo-requests';

describe('toolsText', () => {
    it('names the tools a request asks about as a sentence does', () => {
        expect(toolsText([])).toBe('Sin indicar');
        expect(toolsText(['NOM-035'])).toBe('NOM-035');
        expect(toolsText(['NOM-035', 'NOM-037'])).toBe('NOM-035 y NOM-037');
        expect(toolsText(['NOM-035', 'Ley Silla', 'NOM-037'])).toBe(
            'NOM-035, Ley Silla y NOM-037',
        );
    });
});
