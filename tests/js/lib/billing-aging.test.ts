import { describe, expect, it } from 'vitest';
import {
    agingBadge,
    agingLabel,
    BUCKET_LABELS,
    suggestedAction,
} from '@/lib/billing-aging';

describe('agingLabel', () => {
    it('says how long a balance is past due, or how long is left to pay', () => {
        expect(agingLabel({ bucket: 'current', days: 1 })).toBe(
            'Vence en 1 día',
        );
        expect(agingLabel({ bucket: 'current', days: 12 })).toBe(
            'Vence en 12 días',
        );
        expect(agingLabel({ bucket: '1_30', days: 1 })).toBe('1 día vencido');
        expect(agingLabel({ bucket: '31_60', days: 38 })).toBe(
            '38 días vencido',
        );
    });

    it('says the account is current when it owes nothing', () => {
        expect(agingLabel(null)).toBe('Al corriente');
    });
});

describe('agingBadge', () => {
    it('warns from the first day past due and alarms after 30', () => {
        expect(agingBadge(null)).toBe('success');
        expect(agingBadge({ bucket: 'current', days: 3 })).toBe('success');
        expect(agingBadge({ bucket: '1_30', days: 30 })).toBe('warning');
        expect(agingBadge({ bucket: '31_60', days: 31 })).toBe('destructive');
        expect(agingBadge({ bucket: 'over_60', days: 90 })).toBe('destructive');
    });
});

describe('suggestedAction', () => {
    it('collects after 30 days, reminds before, and only looks while it is not due', () => {
        expect(suggestedAction({ bucket: 'over_60', days: 61 })).toBe(
            'collect',
        );
        expect(suggestedAction({ bucket: '31_60', days: 31 })).toBe('collect');
        expect(suggestedAction({ bucket: '1_30', days: 2 })).toBe('remind');
        expect(suggestedAction({ bucket: 'current', days: 5 })).toBe('view');
        expect(suggestedAction(null)).toBeNull();
    });
});

describe('BUCKET_LABELS', () => {
    it('names the four ages of a balance', () => {
        expect(BUCKET_LABELS).toEqual({
            current: 'Al corriente',
            '1_30': '1–30 días',
            '31_60': '31–60 días',
            over_60: 'Más de 60 días',
        });
    });
});
