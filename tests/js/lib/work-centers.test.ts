import { describe, expect, it } from 'vite-plus/test';

import {
    evaluationStanding,
    guidesShort,
    guidesText,
    staffText,
} from '@/lib/work-centers';

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

describe('guidesShort', () => {
    it('names the guides the way a card shows them', () => {
        expect(guidesShort([])).toBe('—');
        expect(guidesShort(['I'])).toBe('I');
        expect(guidesShort(['I', 'II'])).toBe('I y II');
        expect(guidesShort(['I', 'III'])).toBe('I y III');
    });
});

describe('evaluationStanding', () => {
    it('says an evaluation is running and when it closes', () => {
        expect(
            evaluationStanding({ state: 'running', day: '10 nov 2026' }, 60),
        ).toEqual({
            badge: 'Evaluación en curso',
            variant: 'default',
            next: 'En curso, cierra el 10 nov 2026',
        });
    });

    it('says one is being prepared', () => {
        expect(evaluationStanding({ state: 'draft', day: null }, 30)).toEqual({
            badge: 'Evaluación en preparación',
            variant: 'secondary',
            next: 'En preparación',
        });
    });

    it('says the work center is up to date and by when the next one is due', () => {
        expect(
            evaluationStanding({ state: 'due', day: '29 oct 2028' }, 30),
        ).toEqual({
            badge: 'Al día',
            variant: 'success',
            next: 'Antes del 29 oct 2028',
        });
    });

    it('says when the next one was due and is overdue', () => {
        expect(
            evaluationStanding({ state: 'overdue', day: '2 nov 2026' }, 30),
        ).toEqual({
            badge: 'Evaluación vencida',
            variant: 'destructive',
            next: 'Venció el 2 nov 2026',
        });
    });

    it('asks for a first evaluation where people work, and waits for people where nobody does', () => {
        expect(evaluationStanding(null, 12)).toEqual({
            badge: 'Sin evaluar',
            variant: 'warning',
            next: 'Por programar',
        });
        expect(evaluationStanding(null, 0)).toEqual({
            badge: 'Sin personal',
            variant: 'secondary',
            next: 'Al cargar personas',
        });
    });
});
