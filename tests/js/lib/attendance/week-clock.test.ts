import { describe, expect, it } from 'vitest';
import { weekView } from '@/lib/attendance/week';

const mark = {
    id: 'm1',
    type: 'start',
    time: '07:00',
    nextDay: false,
    corrected: false,
    reason: null,
    evidence: 'strong',
};

const week = {
    name: 'Ana López',
    week: { start: '2026-11-02', end: '2026-11-08' },
    status: 'pending',
    answeredAt: null,
    disputeReason: null,
    resolution: null,
    digest: 'a'.repeat(64),
    blocked: null,
    requests: [],
    summary: {
        ordinaryMinutes: 0,
        overtimeMinutes: 0,
        doubleMinutes: 0,
        tripleMinutes: 0,
        restDayMinutes: 0,
        flags: [],
    },
    confirmUrl: null,
    disputeUrl: null,
    previousUrl: '/a/acme/week/2026-10-26',
    nextUrl: null,
};

const told = (clock: unknown) =>
    weekView({
        ...week,
        days: [
            {
                date: '2026-11-02',
                marks: [{ ...mark, clock }],
                workedMinutes: 0,
                scheduledMinutes: 0,
                overtimeMinutes: 0,
            },
        ],
    })?.days[0]?.marks[0]?.clock;

describe('the zone of a mark in the week the server tells', () => {
    it('keeps the zone of a mark taken away from the center, to say whose hour it is', () => {
        expect(told('America/Tijuana')).toBe('America/Tijuana');
        expect(told(null)).toBeUndefined();
    });
});
