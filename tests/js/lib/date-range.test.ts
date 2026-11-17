import { describe, expect, it } from 'vite-plus/test';

import { pickInRange, periodShortcuts } from '@/lib/date-range';

describe('pickInRange', () => {
    it('starts a new period on the first day picked, leaving the end open', () => {
        expect(
            pickInRange(
                { from: '2026-11-01', to: '2026-11-30' },
                '2026-11-10',
                'from',
            ),
        ).toEqual({
            range: { from: '2026-11-10', to: '' },
            next: 'to',
            done: false,
        });
    });

    it('ends the period on the second day picked', () => {
        expect(
            pickInRange({ from: '2026-11-10', to: '' }, '2026-11-20', 'to'),
        ).toEqual({
            range: { from: '2026-11-10', to: '2026-11-20' },
            next: 'from',
            done: true,
        });
    });

    it('turns the period around when the second day is before the first', () => {
        expect(
            pickInRange({ from: '2026-11-10', to: '' }, '2026-11-03', 'to'),
        ).toEqual({
            range: { from: '2026-11-03', to: '2026-11-10' },
            next: 'from',
            done: true,
        });
    });

    it('takes the same day twice as a period of one day', () => {
        expect(
            pickInRange({ from: '2026-11-10', to: '' }, '2026-11-10', 'to'),
        ).toEqual({
            range: { from: '2026-11-10', to: '2026-11-10' },
            next: 'from',
            done: true,
        });
    });
});

describe('periodShortcuts', () => {
    it('offers this week up to today, last week and last month, weeks from Monday', () => {
        // Wednesday, 2026-11-18.
        expect(periodShortcuts(new Date(2026, 10, 18))).toEqual([
            { label: 'Esta semana', from: '2026-11-16', to: '2026-11-18' },
            { label: 'Semana pasada', from: '2026-11-09', to: '2026-11-15' },
            { label: 'Último mes', from: '2026-10-01', to: '2026-10-31' },
        ]);
    });

    it('starts this week on today when today is Monday, and wraps last month around the year', () => {
        // Monday, 2026-01-05.
        expect(periodShortcuts(new Date(2026, 0, 5))).toEqual([
            { label: 'Esta semana', from: '2026-01-05', to: '2026-01-05' },
            { label: 'Semana pasada', from: '2025-12-29', to: '2026-01-04' },
            { label: 'Último mes', from: '2025-12-01', to: '2025-12-31' },
        ]);
    });

    it('takes a Sunday as the last day of its week', () => {
        expect(periodShortcuts(new Date(2026, 10, 22))[0]).toEqual({
            label: 'Esta semana',
            from: '2026-11-16',
            to: '2026-11-22',
        });
    });
});
