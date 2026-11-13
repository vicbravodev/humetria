import { describe, expect, it } from 'vite-plus/test';

import {
    DEMO_CHOICES,
    DEMO_VIEWS,
    demoEmptyText,
    demoQuery,
    toolsText,
} from '@/lib/demo-requests';

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

describe('demo views', () => {
    it('lists all the views, and offers by hand all but converted', () => {
        expect(DEMO_VIEWS.map((view) => view.value)).toEqual([
            'all',
            'new',
            'contacted',
            'converted',
            'discarded',
        ]);
        expect(DEMO_CHOICES.map((choice) => choice.value)).toEqual([
            'new',
            'contacted',
            'discarded',
        ]);
    });

    it('carries the status in the address except for all of them', () => {
        expect(demoQuery('all')).toEqual({});
        expect(demoQuery('contacted')).toEqual({ status: 'contacted' });
    });

    it('says why a tab is empty', () => {
        expect(demoEmptyText('all')).toContain('formulario de la landing');
        expect(demoEmptyText('new')).toContain('en este estado');
    });
});
