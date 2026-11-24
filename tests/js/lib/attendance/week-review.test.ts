import { describe, expect, it } from 'vitest';
import { weekView } from '@/lib/attendance/week';

const week = {
    name: 'Ana López',
    week: { start: '2026-11-02', end: '2026-11-08' },
    status: 'pending',
    answeredAt: null,
    disputeReason: null,
    resolution: null,
    digest: 'a'.repeat(64),
    blocked: null,
    uncoveredDays: [],
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
    previousUrl: '/me/acme/week/2026-10-26',
    nextUrl: null,
};

/** The first mark of the week the server tells, with `extra` said of it. */
const told = (extra: object) =>
    weekView({
        ...week,
        days: [
            {
                date: '2026-11-02',
                marks: [
                    {
                        id: 'm1',
                        type: 'start',
                        time: '09:00',
                        nextDay: false,
                        corrected: false,
                        reason: null,
                        evidence: 'basic',
                        ...extra,
                    },
                ],
                workedMinutes: 0,
                scheduledMinutes: 0,
                overtimeMinutes: 0,
            },
        ],
    })?.days[0]?.marks[0];

describe('a mark made without a connection in the week the server tells', () => {
    it('keeps that it was made without a connection and where its review stands', () => {
        expect(told({ offline: true, review: 'pending' })).toMatchObject({
            offline: true,
            review: 'pending',
        });
        expect(told({ offline: true, review: 'verified' })).toMatchObject({
            review: 'verified',
        });
    });

    it('keeps why RH said it was incorrect, only with that review', () => {
        expect(
            told({
                offline: true,
                review: 'incorrect',
                reviewNote: 'Entró a las 9:20.',
            }),
        ).toMatchObject({
            review: 'incorrect',
            reviewNote: 'Entró a las 9:20.',
        });
        expect(
            told({ offline: true, review: 'verified', reviewNote: 'x' }),
        ).not.toHaveProperty('reviewNote');
    });

    it('reads a mark with no word of it, or one it does not know, as made online with no review', () => {
        expect(told({})).toMatchObject({ offline: false, review: null });
        expect(told({ offline: 'yes', review: 'maybe' })).toMatchObject({
            offline: false,
            review: null,
        });
    });
});
