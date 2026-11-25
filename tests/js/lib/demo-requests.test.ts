import { describe, expect, it } from 'vite-plus/test';

import {
    DEMO_CHOICES,
    DEMO_VIEWS,
    demoEmptyText,
    demoListQuery,
    demoQuery,
    profileText,
    toolsText,
    workforceText,
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

describe('workforceText and profileText', () => {
    it('writes the size of the company, or says it was not given', () => {
        expect(workforceText('51–100')).toBe('51–100 trabajadores');
        expect(workforceText(null)).toBe('Sin indicar');
    });

    it('writes who the request is for, or says it was not given', () => {
        expect(profileText('Consultor')).toBe('Consultor');
        expect(profileText(null)).toBe('Perfil sin indicar');
    });
});

describe('demo views', () => {
    it('lists all the views, and offers by hand all but converted and scheduled', () => {
        expect(DEMO_VIEWS.map((view) => view.value)).toEqual([
            'all',
            'new',
            'contacted',
            'scheduled',
            'trial',
            'converted',
            'discarded',
        ]);
        // «Demo agendada» asks for its day: it is reached by scheduling, not by choosing it.
        expect(DEMO_CHOICES.map((choice) => choice.value)).toEqual([
            'new',
            'contacted',
            'trial',
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

describe('demoListQuery', () => {
    it('carries the status and what was searched, and nothing that is empty', () => {
        expect(demoListQuery({ status: 'all', q: '', view: 'list' })).toEqual(
            {},
        );
        expect(
            demoListQuery({ status: 'new', q: ' acme ', view: 'list' }),
        ).toEqual({
            status: 'new',
            q: 'acme',
        });
        expect(
            demoListQuery({ status: 'all', q: 'acme', view: 'list' }),
        ).toEqual({
            q: 'acme',
        });
    });
});
