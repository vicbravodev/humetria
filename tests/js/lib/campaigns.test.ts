import { describe, expect, it } from 'vite-plus/test';
import {
    campaignMeta,
    instrumentsText,
    matchesPerson,
    peoplePage,
    peopleQuery,
    progressCaption,
    progressCount,
    progressNote,
    quotasText,
    sampleSize,
} from '@/lib/campaigns';
import type { CampaignProgress, CampaignSummary } from '@/types';

const summary = (changes: Partial<CampaignSummary> = {}): CampaignSummary => ({
    id: 'c1',
    workCenter: 'Planta Norte',
    kind: 'nom035_evaluation',
    kindLabel: 'Evaluación NOM-035',
    status: 'active',
    statusLabel: 'En curso',
    instruments: ['Guía I', 'Guía III'],
    closesOn: '14 nov 2026',
    closedOn: null,
    progress: null,
    ...changes,
});

const progress = (
    completed: number,
    target: number,
    againstSample = true,
): CampaignProgress => ({
    completed,
    target,
    againstSample,
});

describe('instrumentsText', () => {
    it('names one guide or joins several', () => {
        expect(instrumentsText(['Guía I'])).toBe('Guía I');
        expect(instrumentsText(['Guía I', 'Guía III'])).toBe('Guías I y III');
        expect(instrumentsText(['Guía I', 'Guía II', 'Guía III'])).toBe(
            'Guías I, II y III',
        );
        expect(instrumentsText([])).toBe('');
    });
});

describe('sampleSize', () => {
    it('sizes the Guía III sample as the norm does, rounding up', () => {
        expect(sampleSize(51)).toBe(46);
        expect(sampleSize(100)).toBe(80);
        expect(sampleSize(500)).toBe(218);
        expect(sampleSize(10000)).toBe(370);
    });

    it('has no sample for 50 people or fewer', () => {
        expect(sampleSize(50)).toBeNull();
        expect(sampleSize(0)).toBeNull();
    });
});

describe('progress', () => {
    it('counts answers against the sample or everyone invited', () => {
        expect(progressCount(progress(142, 186))).toBe('142 de 186');
        expect(progressCaption(progress(142, 186))).toBe(
            'respuestas de la muestra requerida',
        );
        expect(progressCaption(progress(3, 20, false))).toBe(
            'personas contestaron',
        );
    });

    it('says what is left, or that it is complete', () => {
        expect(progressNote(progress(142, 186))).toBe('Faltan 44 respuestas');
        expect(progressNote(progress(185, 186))).toBe('Falta 1 respuesta');
        expect(progressNote(progress(186, 186))).toBe('Muestra completa');
        expect(progressNote(progress(190, 186))).toBe('Muestra completa');
        expect(progressNote(progress(20, 20, false))).toBe(
            'Todas las personas contestaron',
        );
    });
});

describe('campaignMeta', () => {
    it('gives the guides and the deadline, or when it closed', () => {
        expect(campaignMeta(summary())).toBe(
            'Guías I y III · cierra el 14 nov 2026',
        );
        expect(
            campaignMeta(
                summary({ status: 'closed', closedOn: '20 oct 2026' }),
            ),
        ).toBe('Guías I y III · cerró el 20 oct 2026');
        expect(campaignMeta(summary({ status: 'draft', closesOn: null }))).toBe(
            'Guías I y III · borrador',
        );
    });
});

describe('quotasText', () => {
    it('lists how many of each gender the sample takes', () => {
        expect(
            quotasText([
                { gender: 'female', label: 'Mujeres', count: 45 },
                { gender: 'male', label: 'Hombres', count: 34 },
                { gender: 'other', label: 'Otro', count: 1 },
            ]),
        ).toBe('Mujeres 45 · Hombres 34 · Otro 1');
        expect(quotasText([])).toBe('');
    });
});

describe('matchesPerson', () => {
    const person = {
        id: 'p1',
        name: 'José Martínez',
        area: 'Producción',
        reachable: true,
        inSample: false,
        guides: [],
    };

    it('finds by name or area, ignoring case and accents', () => {
        expect(matchesPerson(person, '')).toBe(true);
        expect(matchesPerson(person, '  ')).toBe(true);
        expect(matchesPerson(person, 'jose')).toBe(true);
        expect(matchesPerson(person, 'MARTÍNEZ')).toBe(true);
        expect(matchesPerson(person, 'produccion')).toBe(true);
        expect(matchesPerson(person, 'ventas')).toBe(false);
    });
});

describe('peopleQuery', () => {
    const filters = {
        status: 'pending',
        guide: null,
        area: null,
        q: '',
    };

    it('leaves out what does not narrow the default list of pending people', () => {
        expect(peopleQuery(filters)).toEqual({});
        expect(peopleQuery({ ...filters, q: '   ' })).toEqual({});
    });

    it('keeps every filter that narrows it', () => {
        expect(
            peopleQuery({
                status: 'all',
                guide: 'nom035.guide-iii',
                area: { value: 'a1', label: 'Ventas' },
                q: ' ana ',
            }),
        ).toEqual({
            status: 'all',
            guide: 'nom035.guide-iii',
            area: 'a1',
            q: 'ana',
        });
    });
});

describe('peoplePage', () => {
    const filters = { status: 'pending', guide: null, area: null, q: '' };
    const people = {
        data: [],
        total: 0,
        current_page: 1,
        last_page: 1,
        prev_page_url: null,
        next_page_url: null,
    };

    it('gives the people to follow up only with where they stand', () => {
        expect(peoplePage(people, [], filters)).toEqual({
            people,
            standings: [],
            filters,
        });
        expect(peoplePage(null, null, filters)).toBeNull();
        expect(peoplePage(people, null, filters)).toBeNull();
    });
});
