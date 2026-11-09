import { describe, expect, it } from 'vitest';
import type { CorrectionDraft } from '@/lib/attendance/correction';
import {
    clockNow,
    correctionBody,
    correctionReason,
    correctionTarget,
    dayAfter,
    requestStatus,
    weekRequests,
} from '@/lib/attendance/correction';

describe('dayAfter', () => {
    it('crosses months and years on the calendar', () => {
        expect(dayAfter('2026-11-08')).toBe('2026-11-09');
        expect(dayAfter('2026-11-30')).toBe('2026-12-01');
        expect(dayAfter('2026-12-31')).toBe('2027-01-01');
    });
});

describe('correctionBody', () => {
    const draft: CorrectionDraft = {
        markId: 'm1',
        type: 'end',
        date: '2026-11-08',
        time: '06:00',
        nextDay: false,
        reason: '  Salí a las 6, el QR no cargaba.  ',
    };

    it('sends the local time of the day, the reason trimmed and the week read', () => {
        expect(correctionBody(draft, '2026-11-02')).toEqual({
            mark_id: 'm1',
            mark_type: 'end',
            proposed_at: '2026-11-08 06:00',
            reason: 'Salí a las 6, el QR no cargaba.',
            start: '2026-11-02',
        });
    });

    it('moves a time after midnight to the next day, and sends no week when there is none', () => {
        expect(
            correctionBody({ ...draft, markId: null, nextDay: true }, null),
        ).toEqual({
            mark_id: null,
            mark_type: 'end',
            proposed_at: '2026-11-09 06:00',
            reason: 'Salí a las 6, el QR no cargaba.',
        });
    });
});

describe('correctionReason', () => {
    it('names a validation error of the form apart from the week', () => {
        expect(correctionReason('invalid')).toBe('invalid_request');
        expect(correctionReason('too_old')).toBe('too_old');
    });
});

describe('requestStatus', () => {
    it('says where a request stands', () => {
        expect(requestStatus('pending')).toBe('En revisión');
        expect(requestStatus('approved')).toBe('Aprobada');
        expect(requestStatus('rejected')).toBe('Rechazada');
        expect(requestStatus('other')).toBe('En revisión');
    });
});

describe('clockNow', () => {
    it("reads the day and the time in the company's time zone, whatever the phone's", () => {
        // 05:50 UTC on 10 November is 23:50 of the 9th in Mexico City.
        expect(
            clockNow(
                new Date(Date.UTC(2026, 10, 10, 5, 50)),
                'America/Mexico_City',
            ),
        ).toEqual({ date: '2026-11-09', time: '23:50' });
        expect(clockNow(new Date(Date.UTC(2026, 10, 10, 0, 5)), 'UTC')).toEqual(
            { date: '2026-11-10', time: '00:05' },
        );
    });
});

describe('correctionTarget', () => {
    it('asks about a mark of the day, by its name and time', () => {
        expect(
            correctionTarget({ id: 'm1', type: 'break_start', time: '14:00' }),
        ).toEqual({
            title: 'Corregir salida a comer de las 14:00',
            markId: 'm1',
            type: 'break_start',
            time: '14:00',
        });
        expect(
            correctionTarget({ id: 'm2', type: 'lunch', time: '14:00' }).type,
        ).toBeNull();
    });

    it('asks which mark the person forgot', () => {
        expect(correctionTarget(null)).toEqual({
            title: 'Agregar una marca que faltó',
            markId: null,
            type: null,
            time: '',
        });
    });
});

describe('weekRequests', () => {
    it('reads the requests it can, and none when there is no list', () => {
        const request = {
            id: 'r1',
            markId: null,
            type: 'end',
            date: '2026-11-03',
            time: '18:00',
            status: 'pending',
            note: null,
        };

        expect(
            weekRequests([request, 3, null, [], { ...request, status: 1 }]),
        ).toEqual([request]);
        expect(weekRequests(undefined)).toEqual([]);
    });
});
