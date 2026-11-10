import { describe, expect, it } from 'vite-plus/test';

import { qualityHeadline, qualityPending } from '@/lib/org-chart/quality';
import type { ReportingIssue } from '@/types';

const issues = (
    withoutManager: number,
    roots: number,
    inactiveManager: number,
): ReportingIssue[] => [
    {
        value: 'without_manager',
        label: 'Sin jefe',
        hint: '',
        count: withoutManager,
        pending: withoutManager > 0,
    },
    {
        value: 'roots',
        label: 'Raíces',
        hint: '',
        count: roots,
        pending: roots > 1,
    },
    {
        value: 'inactive_manager',
        label: 'Jefe dado de baja',
        hint: '',
        count: inactiveManager,
        pending: inactiveManager > 0,
    },
];

describe('org chart data quality', () => {
    it('counts the people to review, leaving out a single root', () => {
        expect(qualityPending(issues(0, 1, 0))).toBe(0);
        expect(qualityPending(issues(3, 1, 1))).toBe(4);
        expect(qualityPending(issues(3, 2, 1))).toBe(6);
    });

    it('says where the reporting line stands', () => {
        expect(qualityHeadline(issues(0, 1, 0))).toBe(
            'La línea de reporte está completa.',
        );
        expect(qualityHeadline(issues(1, 0, 0))).toBe(
            '1 persona por revisar en la línea de reporte.',
        );
        expect(qualityHeadline(issues(3, 2, 1))).toBe(
            '6 personas por revisar en la línea de reporte.',
        );
    });
});
