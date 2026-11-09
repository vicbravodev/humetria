import { describe, expect, it } from 'vitest';
import {
    correctionsQuery,
    resolvedShortcut,
    shortMoment,
    waitingFor,
} from '@/lib/attendance/corrections';

describe('correctionsQuery', () => {
    it('sends only the text and a state other than pending', () => {
        expect(correctionsQuery({ q: '  ', status: 'pending' })).toEqual({});
        expect(correctionsQuery({ q: ' Ana ', status: 'rejected' })).toEqual({
            q: 'Ana',
            status: 'rejected',
        });
    });
});

describe('shortMoment', () => {
    it('reads the local time of the server as it is', () => {
        expect(shortMoment('2026-11-10 23:50')).toBe('10 nov · 23:50');
        expect(shortMoment('2026-01-02 09:00')).toBe('2 ene · 09:00');
    });
});

describe('waitingFor', () => {
    it('says how long a request waits', () => {
        expect(waitingFor(0)).toBe('hoy');
        expect(waitingFor(1)).toBe('ayer');
        expect(waitingFor(4)).toBe('hace 4 días');
    });
});

describe('resolvedShortcut', () => {
    it('has none when nothing was ever resolved', () => {
        expect(
            resolvedShortcut({ pending: 0, approved: 0, rejected: 0 }),
        ).toBeNull();
    });

    it('goes to the approved ones, counting both kinds of resolved', () => {
        expect(
            resolvedShortcut({ pending: 0, approved: 9, rejected: 3 }),
        ).toEqual({ status: 'approved', label: 'Ver las 12 resueltas' });
    });

    it('goes to the rejected ones when none was approved, in singular for one', () => {
        expect(
            resolvedShortcut({ pending: 0, approved: 0, rejected: 1 }),
        ).toEqual({ status: 'rejected', label: 'Ver la resuelta' });
    });
});
