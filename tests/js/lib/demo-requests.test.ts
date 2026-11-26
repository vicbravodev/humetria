import { describe, expect, it } from 'vite-plus/test';

import {
    DEMO_CHOICES,
    DEMO_ORIGINS,
    DEMO_SIZES,
    DEMO_VIEWS,
    demoCardLine,
    demoEmptyText,
    filterChipText,
    demoListQuery,
    demoMetricTiles,
    firstResponseText,
    unattendedText,
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

describe('the figures of the console', () => {
    it('writes the time to the first answer in hours, then in days', () => {
        expect(firstResponseText(null)).toBe('—');
        expect(firstResponseText(0.4)).toBe('Menos de 1 h');
        expect(firstResponseText(9.2)).toBe('9 h');
        expect(firstResponseText(47.6)).toBe('48 h');
        expect(firstResponseText(48)).toBe('2 d');
        expect(firstResponseText(80)).toBe('3 d');
    });

    it('says how many new requests wait more than 48 hours', () => {
        expect(unattendedText(0)).toBe('Ninguna con más de 48 h');
        expect(unattendedText(1)).toBe('1 con más de 48 h');
        expect(unattendedText(3)).toBe('3 con más de 48 h');
    });
});

describe('demoMetricTiles', () => {
    it('holds the four tiles while the figures arrive', () => {
        expect(demoMetricTiles(undefined).map((tile) => tile.title)).toEqual([
            'Nuevas',
            'Tiempo de primera respuesta',
            'Conversión a empresa',
            'Fuente principal',
        ]);
        expect(demoMetricTiles(undefined)[0]?.figure).toBeNull();
    });

    it('writes each figure, and a dash where there is nothing to know yet', () => {
        const tiles = demoMetricTiles({
            new: 3,
            unattended: 1,
            first_response_hours: 9,
            conversion: 31,
            source: { label: 'google', share: 58 },
        });

        expect(tiles.map((tile) => [tile.figure, tile.caption])).toEqual([
            ['3', '1 con más de 48 h'],
            ['9 h', 'Mediana, últimos 90 días'],
            ['31 %', 'Últimos 90 días'],
            ['google', '58 % de las solicitudes, 90 días'],
        ]);

        const empty = demoMetricTiles({
            new: 0,
            unattended: 0,
            first_response_hours: null,
            conversion: null,
            source: null,
        });

        expect(empty.map((tile) => tile.figure)).toEqual(['0', '—', '—', '—']);
        expect(empty[3]?.caption).toBe('Ninguna dijo de dónde llegó');
    });
});

describe('the filters of the console (tablero Demos)', () => {
    it('carries the funnel and each filter chosen in the address, and nothing that is empty', () => {
        expect(
            demoListQuery({
                status: 'all',
                q: '',
                view: 'board',
                origin: 'ad',
                owner: '',
                size: '51_100',
            }),
        ).toEqual({ view: 'board', origin: 'ad', size: '51_100' });
        expect(
            demoListQuery({
                status: 'new',
                q: '',
                view: 'list',
                origin: '',
                owner: 'none',
                size: '',
            }),
        ).toEqual({ status: 'new', owner: 'none' });
    });

    it('offers the origins and the sizes the server knows', () => {
        expect(DEMO_ORIGINS.map((origin) => origin.value)).toEqual([
            'ad',
            'campaign',
            'search',
            'referral',
            'unknown',
        ]);
        expect(DEMO_SIZES.map((size) => size.value)).toEqual([
            '1_15',
            '16_50',
            '51_100',
            '101_500',
            '501_plus',
        ]);
    });

    it('names a chip by what it narrows to, or by itself when it narrows nothing', () => {
        expect(filterChipText('Tamaño', DEMO_SIZES, '')).toBe('Tamaño');
        expect(filterChipText('Tamaño', DEMO_SIZES, '16_50')).toBe(
            'Tamaño: 16–50',
        );
        expect(filterChipText('Origen', DEMO_ORIGINS, 'ad')).toBe(
            'Origen: Anuncio',
        );
        expect(filterChipText('Origen', DEMO_ORIGINS, 'tv')).toBe('Origen');
    });
});

describe('demoCardLine', () => {
    it('writes the size under its company, and who it is for when not the company itself', () => {
        expect(demoCardLine('51–100', 'Empresa')).toBe('51–100 trabajadores');
        expect(demoCardLine('51–100', 'Sindicato')).toBe(
            '51–100 trabajadores · Sindicato',
        );
        expect(demoCardLine(null, 'Consultor')).toBe('Consultor');
        expect(demoCardLine('1–15', null)).toBe('1–15 trabajadores');
        expect(demoCardLine(null, null)).toBe('Tamaño sin indicar');
    });
});
