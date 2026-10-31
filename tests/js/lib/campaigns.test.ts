import { describe, expect, it } from 'vite-plus/test';
import {
    campaignMeta,
    guideBreakdown,
    instrumentsText,
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
    launchedOn: null,
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
        from: null,
        to: null,
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

describe('guideBreakdown', () => {
    const guide = {
        key: 'nom035.guide-ii',
        title: 'Guía II',
        invited: 0,
        completed: 0,
        pending: 0,
        unreachable: 0,
        cancelled: 0,
    };

    it('says where those who did not answer stand, leaving out what is zero', () => {
        expect(
            guideBreakdown({
                ...guide,
                pending: 12,
                unreachable: 3,
                cancelled: 2,
            }),
        ).toBe(
            '12 pendientes · 3 sin correo · 2 canceladas por bajas o cambios de centro',
        );
        expect(
            guideBreakdown({
                ...guide,
                pending: 1,
                unreachable: 1,
                cancelled: 1,
            }),
        ).toBe(
            '1 pendiente · 1 sin correo · 1 cancelada por bajas o cambios de centro',
        );
        expect(guideBreakdown(guide)).toBe('Nadie pendiente');
    });
});
