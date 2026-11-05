import { describe, expect, it } from 'vitest';
import {
    caseSteps,
    daysUntil,
    dueText,
    elapsed,
    isUrgent,
    shortDay,
} from '@/lib/complaints/deadline';

describe('daysUntil', () => {
    it('counts whole calendar days, negative once it passed', () => {
        expect(daysUntil('2026-11-20', '2026-11-21')).toBe(1);
        expect(daysUntil('2026-11-20', '2026-11-20')).toBe(0);
        expect(daysUntil('2026-11-20', '2026-11-17')).toBe(-3);
    });

    it('counts across a change of month and of the clock', () => {
        expect(daysUntil('2026-10-30', '2026-11-02')).toBe(3);
    });

    it('is nothing when a day is not a date', () => {
        expect(daysUntil('ayer', '2026-11-21')).toBeNull();
    });
});

describe('dueText', () => {
    it('says when the deadline falls, from today', () => {
        expect(dueText('Acuse', '2026-11-20', '2026-11-22')).toBe(
            'Acuse vence en 2 días',
        );
        expect(dueText('Acuse', '2026-11-20', '2026-11-21')).toBe(
            'Acuse vence mañana',
        );
        expect(dueText('Respuesta', '2026-11-20', '2026-11-20')).toBe(
            'Respuesta vence hoy',
        );
        expect(dueText('Acuse', '2026-11-20', '2026-11-19')).toBe(
            'Acuse venció hace 1 día',
        );
        expect(dueText('Acuse', '2026-11-20', '2026-11-15')).toBe(
            'Acuse venció hace 5 días',
        );
    });

    it('says nothing precise of a day that is not a date', () => {
        expect(dueText('Acuse', '2026-11-20', '')).toBe('Acuse sin plazo');
    });
});

describe('isUrgent', () => {
    it('is a deadline of today or tomorrow, or one that passed', () => {
        expect(isUrgent('2026-11-20', '2026-11-21')).toBe(true);
        expect(isUrgent('2026-11-20', '2026-11-20')).toBe(true);
        expect(isUrgent('2026-11-20', '2026-11-10')).toBe(true);
        expect(isUrgent('2026-11-20', '2026-11-22')).toBe(false);
        expect(isUrgent('2026-11-20', '')).toBe(false);
    });
});

describe('elapsed', () => {
    it('is how much of the time between receipt and the deadline went by, from 0 to 1', () => {
        expect(elapsed('2026-11-18', '2026-11-21', '2026-11-18')).toBe(0);
        expect(elapsed('2026-11-18', '2026-11-21', '2026-11-20')).toBeCloseTo(
            2 / 3,
        );
        expect(elapsed('2026-11-18', '2026-11-21', '2026-11-25')).toBe(1);
        expect(elapsed('2026-11-18', '2026-11-18', '2026-11-18')).toBe(1);
    });

    it('is nothing gone by when a day is not a date', () => {
        expect(elapsed('', '2026-11-21', '2026-11-20')).toBe(0);
    });
});

describe('shortDay', () => {
    it('writes a day short, with its weekday, as the inbox reads it', () => {
        expect(shortDay('2026-11-02')).toBe('lun 2 nov');
    });

    it('keeps a text that is not a date', () => {
        expect(shortDay('pronto')).toBe('pronto');
    });
});

describe('caseSteps', () => {
    const deadlines = {
        acknowledgeBy: '2026-11-05',
        respondBy: '2026-12-02',
        acknowledgedOn: null,
        closedOn: null,
    };

    it('marks a new case received, and what is left to do', () => {
        expect(caseSteps('new', '2026-11-02', deadlines)).toEqual([
            { label: 'Recibida', day: 'lun 2 nov', done: true },
            { label: 'Acuse enviado', day: null, done: false },
            { label: 'En revisión', day: null, done: false },
            { label: 'Cerrada', day: null, done: false },
        ]);
    });

    it('marks the acknowledgement with its day once sent', () => {
        expect(
            caseSteps('new', '2026-11-02', {
                ...deadlines,
                acknowledgedOn: '2026-11-04',
            }).map(({ done, day }) => [done, day]),
        ).toEqual([
            [true, 'lun 2 nov'],
            [true, 'mié 4 nov'],
            [false, null],
            [false, null],
        ]);
    });

    it('marks a case in review, and a closed one with its day', () => {
        expect(
            caseSteps('in_review', '2026-11-02', {
                ...deadlines,
                acknowledgedOn: '2026-11-04',
            }).map(({ done }) => done),
        ).toEqual([true, true, true, false]);
        expect(
            caseSteps('closed', '2026-11-02', {
                ...deadlines,
                acknowledgedOn: '2026-11-04',
                closedOn: '2026-11-10',
            }).at(3),
        ).toEqual({ label: 'Cerrada', day: 'mar 10 nov', done: true });
    });
});
