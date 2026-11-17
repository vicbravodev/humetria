import { describe, expect, it } from 'vite-plus/test';

import {
    deducedShift,
    draftOf,
    draftInputs,
    isRest,
    sharedStart,
    startsDiffer,
    withHours,
    withRest,
    withStart,
} from '@/lib/attendance/schedule-draft';

const week = (...days: number[]) =>
    Object.fromEntries(days.map((minutes, at) => [String(at + 1), minutes]));

const officeWeek = draftOf(week(480, 480, 480, 480, 480, 0, 0), {
    1: 540,
    2: 540,
    3: 540,
    4: 540,
    5: 540,
});

describe('a week being written', () => {
    it('starts from the minutes and starts saved, as the fields take them', () => {
        expect(officeWeek.hours).toEqual({
            1: '8',
            2: '8',
            3: '8',
            4: '8',
            5: '8',
            6: '0',
            7: '0',
        });
        expect(officeWeek.starts['1']).toBe('09:00');
        expect(officeWeek.starts['6']).toBe('');
    });

    it('sends the minutes and start of each day; a rest day has no start', () => {
        const typed = withHours(officeWeek, '3', '7,5');

        expect(draftInputs(withRest(typed, '5', true))).toEqual({
            minutes: {
                1: '480',
                2: '480',
                3: '450',
                4: '480',
                5: '0',
                6: '0',
                7: '0',
            },
            starts: {
                1: '540',
                2: '540',
                3: '540',
                4: '540',
                5: '',
                6: '',
                7: '',
            },
        });
    });

    it('sends an odd number of hours as it is, for the server to refuse', () => {
        expect(
            draftInputs(withHours(officeWeek, '1', 'ocho')).minutes['1'],
        ).toBe('');
    });
});

describe('a rest day', () => {
    it('is a day of 0 hours, not one whose hours are being typed', () => {
        expect(isRest(officeWeek, '6')).toBe(true);
        expect(isRest(officeWeek, '1')).toBe(false);
        expect(isRest(withHours(officeWeek, '1', ''), '1')).toBe(false);
    });

    it('is a day of 0 hours with no start', () => {
        const rested = withRest(officeWeek, '1', true);

        expect(rested.hours['1']).toBe('0');
        expect(rested.starts['1']).toBe('');
    });

    it('back to work takes the hours of the other days and the shared start', () => {
        const worked = withRest(officeWeek, '6', false);

        expect(worked.hours['6']).toBe('8');
        expect(worked.starts['6']).toBe('09:00');
    });

    it('back to work with nobody else working takes 8 h and no start', () => {
        const none = draftOf(week(0, 0, 0, 0, 0, 0, 0), null);
        const worked = withRest(none, '2', false);

        expect(worked.hours['2']).toBe('8');
        expect(worked.starts['2']).toBe('');
    });
});

describe('the one start time of the week', () => {
    it('is the time all working days share, rest days left out', () => {
        expect(sharedStart(officeWeek)).toBe('09:00');
    });

    it('is empty when the days differ or none has one', () => {
        expect(
            sharedStart({
                ...officeWeek,
                starts: { ...officeWeek.starts, 2: '10:00' },
            }),
        ).toBe('');
        expect(sharedStart(draftOf(week(480, 0, 0, 0, 0, 0, 0), null))).toBe(
            '',
        );
    });

    it('differs when working days have different starts, not when none has one', () => {
        expect(startsDiffer(officeWeek)).toBe(false);
        expect(
            startsDiffer({
                ...officeWeek,
                starts: { ...officeWeek.starts, 2: '' },
            }),
        ).toBe(true);
        expect(startsDiffer(draftOf(week(480, 480, 0, 0, 0, 0, 0), null))).toBe(
            false,
        );
    });

    it('set once, it goes to every working day and none of the rest', () => {
        const set = withStart(officeWeek, '07:00');

        expect(set.starts).toEqual({
            1: '07:00',
            2: '07:00',
            3: '07:00',
            4: '07:00',
            5: '07:00',
            6: '',
            7: '',
        });
    });
});

describe('the shift a week is (art. 60)', () => {
    const at = (start: number, minutes: number) =>
        draftOf(week(minutes, minutes, 0, 0, 0, 0, 0), { 1: start, 2: start });

    it('is day between 6:00 and 20:00', () => {
        expect(deducedShift(at(360, 480))).toBe('day');
        expect(deducedShift(at(720, 480))).toBe('day');
    });

    it('is night between 20:00 and 6:00, past midnight too', () => {
        expect(deducedShift(at(1320, 420))).toBe('night');
        expect(deducedShift(at(1200, 600))).toBe('night');
        expect(deducedShift(at(0, 360))).toBe('night');
    });

    it('is mixed when it takes both, with less than 3 h 30 min of night', () => {
        expect(deducedShift(at(900, 450))).toBe('mixed');
        expect(deducedShift(at(300, 480))).toBe('mixed');
    });

    it('is night when it takes 3 h 30 min of night or more', () => {
        expect(deducedShift(at(1020, 450))).toBe('night');
    });

    it('is the most nightly of its days', () => {
        const days = draftOf(week(480, 420, 0, 0, 0, 0, 0), {
            1: 540,
            2: 1320,
        });

        expect(deducedShift(days)).toBe('night');
    });

    it('is unknown without a start on any working day', () => {
        expect(
            deducedShift(draftOf(week(480, 480, 0, 0, 0, 0, 0), null)),
        ).toBeNull();
    });
});
