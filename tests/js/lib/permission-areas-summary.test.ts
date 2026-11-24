import { describe, expect, it } from 'vite-plus/test';
import {
    AREAS,
    accessCount,
    beyondRole,
    areasFor,
    phraseOf,
    rowTags,
    sensitiveLevel,
    summarize,
} from '@/lib/permission-areas';
import { area, groups } from '../support/permission-areas';

describe('what a level says', () => {
    it('is the phrase of the level held', () => {
        expect(phraseOf(area('vacations'), ['view-vacations'], groups)).toBe(
            area('vacations').levels[1]?.phrase,
        );
    });

    it('names what is held when no level matches', () => {
        expect(phraseOf(area('vacations'), ['manage-vacations'], groups)).toBe(
            'Tiene una combinación propia: Etiqueta manage-vacations. Elige un nivel para ordenarla.',
        );
    });

    it('is sensitive when any of its permissions is', () => {
        expect(sensitiveLevel(area('complaints').levels[1], groups)).toBe(true);
        expect(sensitiveLevel(area('vacations').levels[2], groups)).toBe(false);
        expect(sensitiveLevel(undefined, groups)).toBe(false);
    });
});

describe('the summary', () => {
    const shown = areasFor(groups).map((item) => item.area);

    it('says what the role manages, what it sees and what else it does', () => {
        expect(
            summarize({
                areas: shown,
                held: [
                    'view-vacations',
                    'manage-vacations',
                    'view-dashboard',
                    'view-nom035-route',
                    'view-interventions',
                    'view-disclosure',
                    'handle-complaints',
                    'view-trauma-screenings',
                ],
                subject: 'Quien tenga este rol',
            }),
        ).toBe(
            'Quien tenga este rol administra Vacaciones y ve Panel y NOM-035. También atiende quejas asignadas y ve los tamizajes de la Guía I.',
        );
    });

    it('counts the areas past the third it sees', () => {
        expect(
            summarize({
                areas: AREAS,
                held: [
                    'view-dashboard',
                    'view-work-centers',
                    'view-worker-access',
                    'view-climate',
                    'view-pulse',
                ],
                subject: 'Quien tenga este rol',
            }),
        ).toBe(
            'Quien tenga este rol ve Panel, Centros de trabajo y empleados, Acceso de trabajadores y 2 áreas más.',
        );
        expect(
            summarize({
                areas: AREAS,
                held: [
                    'view-dashboard',
                    'view-work-centers',
                    'view-worker-access',
                    'view-climate',
                ],
                subject: 'Quien tenga este rol',
            }),
        ).toBe(
            'Quien tenga este rol ve Panel, Centros de trabajo y empleados, Acceso de trabajadores y 1 área más.',
        );
    });

    it('joins two verbs with a comma when the first list already has «y»', () => {
        expect(
            summarize({
                areas: AREAS,
                held: [
                    'view-vacations',
                    'manage-vacations',
                    'view-pulse',
                    'manage-pulse',
                    'view-dashboard',
                ],
                subject: 'Quien tenga este rol',
            }),
        ).toBe(
            'Quien tenga este rol administra Pulso y Vacaciones, y ve Panel.',
        );
    });
});

describe('the summary at its edges', () => {
    it('says only the other things when nothing is seen nor managed', () => {
        expect(
            summarize({
                areas: AREAS,
                held: ['use-assistant'],
                subject: 'Quien tenga este rol',
            }),
        ).toBe('Quien tenga este rol conversa con Humi.');
    });

    it('says when there is nothing', () => {
        expect(
            summarize({
                areas: AREAS,
                held: [],
                subject: 'Quien tenga este rol',
            }),
        ).toBe('Quien tenga este rol no tiene acceso a ninguna área.');
    });

    it('reads a set no level matches by the level wholly held under it', () => {
        expect(
            summarize({
                areas: AREAS,
                held: [
                    'view-vacations',
                    'manage-vacations',
                    'view-attendance',
                    'manage-campaigns',
                ],
                subject: 'Quien tenga este rol',
            }),
        ).toBe('Quien tenga este rol administra Vacaciones y ve Asistencia.');
    });
});

describe('how many areas', () => {
    it('counts those with any access, a mixed one included, out of those shown', () => {
        expect(
            accessCount(AREAS, [
                'view-vacations',
                'manage-campaigns',
                'view-trauma-screenings',
            ]),
        ).toEqual({ on: 2, total: AREAS.length });
    });
});

describe('what a person has besides their role', () => {
    it('are the areas where any permission is their own, an extra included', () => {
        expect(
            beyondRole(AREAS, [
                'manage-vacations',
                'view-trauma-screenings',
            ]).map((item) => item.key),
        ).toEqual(['nom035', 'vacations']);
        expect(beyondRole(AREAS, [])).toEqual([]);
    });
});

describe('the summary without a subject', () => {
    it('starts with the verb, for a column of a list', () => {
        expect(
            summarize({
                areas: AREAS,
                held: ['view-vacations', 'handle-complaints'],
                subject: '',
            }),
        ).toBe('Ve Vacaciones. También atiende quejas asignadas.');
        expect(summarize({ areas: AREAS, held: [], subject: '' })).toBe(
            'No tiene acceso a ninguna área.',
        );
    });
});

describe('the tags of a row', () => {
    it('says «Sensible» for a sensitive level chosen above the floor', () => {
        expect(
            rowTags({
                area: area('complaints'),
                held: ['handle-complaints'],
                base: undefined,
                groups,
            }),
        ).toEqual({ additional: false, sensitive: true });
        expect(
            rowTags({
                area: area('complaints'),
                held: ['handle-complaints'],
                base: ['handle-complaints'],
                groups,
            }),
        ).toEqual({ additional: false, sensitive: false });
    });

    it('says «Adicional» where a person has more than their role', () => {
        expect(
            rowTags({
                area: area('vacations'),
                held: ['view-vacations', 'manage-vacations'],
                base: ['view-vacations'],
                groups,
            }),
        ).toEqual({ additional: true, sensitive: false });
    });
});
