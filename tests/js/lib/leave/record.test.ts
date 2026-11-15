import { describe, expect, it } from 'vitest';
import {
    entryDays,
    milestones,
    openStatement,
    owedDays,
    tabCounts,
} from '@/lib/leave/record';

describe('entryDays', () => {
    it('signs the days that enter and leave', () => {
        expect(entryDays(3)).toBe('+3 días');
        expect(entryDays(1)).toBe('+1 día');
        expect(entryDays(-1)).toBe('−1 día');
        expect(entryDays(-12)).toBe('−12 días');
    });
});

describe('owedDays', () => {
    it('adds the pending and the proportional days to two decimals', () => {
        expect(owedDays(16, 5.98)).toBe(21.98);
        expect(owedDays(0.1, 0.2)).toBe(0.3);
        expect(owedDays(-2, 5)).toBe(3);
    });
});

const period = {
    id: 'p',
    year: 2026,
    anniversaryOn: '2026-11-01',
    enjoyBy: '2027-05-01',
    prescribesOn: '2028-05-01',
    granted: 25,
    available: 23,
    mayPrescribe: false,
    overdue: false,
};

describe('milestones', () => {
    it('reads the period as three dates: given, to enjoy before, and when it prescribes', () => {
        expect(milestones(period)).toEqual([
            { title: 'Otorgado', detail: '1 nov 2026 · 25 días' },
            { title: 'A disfrutar antes del', detail: '1 may 2027' },
            { title: 'Prescribe el', detail: '1 may 2028' },
        ]);
    });

    it('says «Vencido» next to the window that closed with days still in the period', () => {
        expect(milestones({ ...period, overdue: true })[1]).toEqual({
            title: 'A disfrutar antes del',
            detail: '1 may 2027 · Vencido',
        });
    });

    it('says one day in the singular', () => {
        expect(milestones({ ...period, granted: 1 })[0]?.detail).toBe(
            '1 nov 2026 · 1 día',
        );
    });
});

describe('openStatement', () => {
    const statement = (year: number, acknowledgedOn: string | null) => ({
        id: String(year),
        year,
        issuedOn: '2026-11-01',
        acknowledgedOn,
    });

    it('is the newest statement nobody confirmed', () => {
        expect(
            openStatement([
                statement(2026, null),
                statement(2025, null),
                statement(2024, '2025-01-01'),
            ])?.year,
        ).toBe(2026);
    });

    it('is nothing when all were confirmed or there are none', () => {
        expect(openStatement([statement(2024, '2025-01-01')])).toBeNull();
        expect(openStatement([])).toBeNull();
    });
});

describe('tabCounts', () => {
    it('counts what each tab holds, with the totals the server gives for the paged ones', () => {
        expect(
            tabCounts({
                movements: 3,
                requests: 1,
                statements: [{ acknowledgedOn: null }],
                periods: 4,
            }),
        ).toEqual({
            movements: 3,
            requests: 1,
            statements: 1,
            periods: 4,
        });
    });
});
