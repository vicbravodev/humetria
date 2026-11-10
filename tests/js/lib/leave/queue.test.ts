import { describe, expect, it } from 'vitest';
import { daysLine, queueQuery } from '@/lib/leave/queue';
import type { LeaveQueueFilters } from '@/types';

const plain: LeaveQueueFilters = {
    q: '',
    center: null,
    area: null,
    tab: 'attend',
};

describe('queueQuery', () => {
    it('leaves a plain queue with a plain address', () => {
        expect(queueQuery(plain)).toEqual({});
    });

    it('keeps only what narrows it', () => {
        expect(
            queueQuery({
                q: '  ana ',
                center: { value: 'c1', label: 'Planta' },
                area: { value: 'a1', label: 'Producción' },
                tab: 'rejected',
            }),
        ).toEqual({ tab: 'rejected', q: 'ana', center: 'c1', area: 'a1' });
    });
});

describe('daysLine', () => {
    it('says a leave uses no days', () => {
        expect(daysLine({ hadBalance: null, periods: [], shortBy: 0 })).toBe(
            'No usa días de vacaciones',
        );
    });

    it('says whether the person had the days and from which periods', () => {
        expect(daysLine({ hadBalance: true, periods: [], shortBy: 0 })).toBe(
            'Tenía días',
        );
        expect(
            daysLine({ hadBalance: true, periods: [2025, 2026], shortBy: 0 }),
        ).toBe('Tenía días · del periodo 2025 y 2026');
    });

    it('says how many days were missing', () => {
        expect(daysLine({ hadBalance: false, periods: [], shortBy: 1 })).toBe(
            'No tenía días · le faltaban 1 día',
        );
        expect(daysLine({ hadBalance: false, periods: [], shortBy: 0 })).toBe(
            'No tenía días · sin saldo al pedirla',
        );
    });
});
