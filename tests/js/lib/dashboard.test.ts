import { describe, expect, it } from 'vite-plus/test';
import {
    closingKpi,
    cycleTexts,
    dashboardView,
    greeting,
    highRiskShare,
    missingKpi,
    nextStepTexts,
    panelSummary,
    participationShare,
    pendingText,
} from '@/lib/dashboard';
import type { CycleRow, NextStep } from '@/types';

const step = (changes: Partial<NextStep>): NextStep => ({
    kind: 'launch',
    campaignId: 'c1',
    workCenter: 'Planta Querétaro',
    workers: 30,
    instruments: ['Guía I', 'Guía II'],
    people: null,
    ...changes,
});

const row = (changes: Partial<CycleRow>): CycleRow => ({
    id: 'w1',
    name: 'Planta Querétaro',
    workers: 30,
    instruments: ['Guía I', 'Guía II'],
    standing: 'up_to_date',
    from: '29 oct 2026',
    until: '29 oct 2028',
    elapsed: 0,
    ...changes,
});

describe('greeting', () => {
    it('greets by the first name', () => {
        expect(greeting('Rita Ramírez Hernández')).toBe('Hola, Rita');
        expect(greeting('  Rita ')).toBe('Hola, Rita');
        expect(greeting('')).toBe('Hola');
    });
});

describe('panelSummary', () => {
    it('says what is in course and what is ready to launch', () => {
        expect(panelSummary({ active: 1, drafts: 1 })).toBe(
            'Tienes una evaluación en curso y un borrador listo para lanzar.',
        );
        expect(panelSummary({ active: 2, drafts: 0 })).toBe(
            'Tienes 2 evaluaciones en curso.',
        );
        expect(panelSummary({ active: 0, drafts: 3 })).toBe(
            'Tienes 3 borradores listos para lanzar.',
        );
        expect(panelSummary({ active: 0, drafts: 0 })).toBe(
            'No tienes evaluaciones en curso.',
        );
    });
});

describe('closingKpi', () => {
    it('counts the days to the last one', () => {
        expect(closingKpi(11)).toEqual({
            value: '11 días',
            caption: 'para el cierre',
        });
        expect(closingKpi(1)).toEqual({
            value: '1 día',
            caption: 'para el cierre',
        });
        expect(closingKpi(0)).toEqual({
            value: 'Hoy',
            caption: 'es el último día',
        });
    });
});

describe('missingKpi', () => {
    it('says how many answers are missing, never fewer than none', () => {
        expect(missingKpi({ completed: 18, target: 60 })).toEqual({
            value: '42',
            caption: 'faltan por contestar',
        });
        expect(missingKpi({ completed: 59, target: 60 })).toEqual({
            value: '1',
            caption: 'falta por contestar',
        });
        expect(missingKpi({ completed: 61, target: 60 })).toEqual({
            value: '0',
            caption: 'faltan por contestar',
        });
    });
});

describe('nextStepTexts', () => {
    it('writes a draft to launch', () => {
        expect(nextStepTexts(step({}))).toEqual({
            title: 'Lanza la evaluación de Planta Querétaro',
            detail: 'Borrador listo · 30 personas · Guías I y II',
            action: 'Continuar',
        });
        expect(nextStepTexts(step({ workers: null })).detail).toBe(
            'Borrador listo · Guías I y II',
        );
        expect(nextStepTexts(step({ workers: 1 })).detail).toBe(
            'Borrador listo · 1 persona · Guías I y II',
        );
    });

    it('writes a report to complete', () => {
        expect(nextStepTexts(step({ kind: 'report' }))).toEqual({
            title: 'Completa el informe 7.7 de Planta Querétaro',
            detail: 'Faltan conclusiones, recomendaciones y responsable',
            action: 'Completar',
        });
    });

    it('writes how many people to channel', () => {
        expect(nextStepTexts(step({ kind: 'screenings', people: 2 }))).toEqual({
            title: '2 personas requieren valoración clínica',
            detail: 'Guía I · Planta Querétaro · canalízalas a atención',
            action: 'Revisar',
        });
        expect(nextStepTexts(step({ kind: 'screenings', people: 1 }))).toEqual({
            title: '1 persona requiere valoración clínica',
            detail: 'Guía I · Planta Querétaro · canalízala a atención',
            action: 'Revisar',
        });
    });
});

describe('pendingText', () => {
    it('counts the pending steps', () => {
        expect(pendingText(1)).toBe('1 pendiente');
        expect(pendingText(3)).toBe('3 pendientes');
    });
});

describe('cycleTexts', () => {
    it('writes each standing with its badge', () => {
        expect(cycleTexts(row({}))).toEqual({
            from: 'Evaluada el 29 oct 2026',
            until: 'Siguiente antes del 29 oct 2028',
            badge: { label: 'Al día', variant: 'success' },
        });
        expect(
            cycleTexts(
                row({
                    standing: 'in_course',
                    from: '27 oct 2026',
                    until: '10 nov 2026',
                }),
            ),
        ).toEqual({
            from: 'Lanzada el 27 oct 2026',
            until: 'Cierra el 10 nov 2026',
            badge: { label: 'En curso', variant: 'default' },
        });
        expect(cycleTexts(row({ standing: 'overdue' }))).toEqual({
            from: 'Evaluada el 29 oct 2026',
            until: 'Debía evaluarse antes del 29 oct 2028',
            badge: { label: 'Vencida', variant: 'destructive' },
        });
        expect(
            cycleTexts(row({ standing: 'never', from: null, until: null })),
        ).toEqual({
            from: 'Aún sin evaluar',
            until: '',
            badge: { label: 'Sin evaluar', variant: 'warning' },
        });
    });
});

describe('participationShare', () => {
    it('writes who answered of how many, with the share', () => {
        expect(participationShare({ completed: 29, target: 30 })).toBe(
            '29 de 30 · 97 %',
        );
    });
});

describe('highRiskShare', () => {
    it('gives the share in Alto or Muy alto', () => {
        expect(highRiskShare([2, 6, 3, 3, 15])).toBe('62 %');
        expect(highRiskShare([0, 0, 0, 0, 0])).toBe('0 %');
    });
});

describe('dashboardView', () => {
    const view = (changes: Partial<Parameters<typeof dashboardView>[0]>) =>
        dashboardView({
            hasWorkCenters: true,
            hasActive: false,
            steps: 0,
            closed: 0,
            ...changes,
        });

    it('points to the work centers first, then waits for the first campaign', () => {
        expect(view({ hasWorkCenters: false, hasActive: true })).toBe(
            'no-work-centers',
        );
        expect(view({})).toBe('no-campaigns');
        expect(view({ hasActive: true })).toBe('panel');
        expect(view({ steps: 1 })).toBe('panel');
        expect(view({ closed: 1 })).toBe('panel');
    });
});
