import { describe, expect, it } from 'vite-plus/test';

import { orderText, qualityQuery, qualityTabs } from '@/lib/org-chart/quality';
import type { ReportingIssue } from '@/types';

const issues: ReportingIssue[] = [
    { value: 'all', label: 'Todas', count: 212, pending: false },
    { value: 'no_manager', label: 'Sin jefe', count: 3, pending: true },
    {
        value: 'many_reports',
        label: 'Más de 15 directos',
        count: 0,
        pending: false,
    },
    {
        value: 'inactive_manager',
        label: 'Jefe dado de baja',
        count: 1,
        pending: true,
    },
];

describe('qualityQuery', () => {
    it('always names the view, and keeps what narrows the list only when it narrows', () => {
        expect(qualityQuery({ q: '', work_center: null }, 'all')).toEqual({
            issue: 'all',
        });
        expect(
            qualityQuery(
                { q: '  ana ', work_center: { value: 'w1', label: 'Oficina' } },
                'no_manager',
            ),
        ).toEqual({ issue: 'no_manager', q: 'ana', work_center: 'w1' });
    });
});

describe('qualityTabs', () => {
    const tabs = qualityTabs(issues, 'no_manager', {
        q: 'ana',
        work_center: null,
    });

    it('gives each view with its count, the open one marked, and what asks for action flagged', () => {
        expect(
            tabs.map(({ label, count, active, needsAction }) => [
                label,
                count,
                active,
                needsAction,
            ]),
        ).toEqual([
            ['Todas', 212, false, false],
            ['Sin jefe', 3, true, true],
            ['Más de 15 directos', 0, false, false],
            ['Jefe dado de baja', 1, false, true],
        ]);
    });

    it('keeps the search and the work center when changing view', () => {
        expect(tabs.map(({ query }) => query)).toEqual([
            { issue: 'all', q: 'ana' },
            { issue: 'no_manager', q: 'ana' },
            { issue: 'many_reports', q: 'ana' },
            { issue: 'inactive_manager', q: 'ana' },
        ]);
    });
});

describe('orderText', () => {
    it('says the line is complete, for many people and for one', () => {
        expect(orderText(212)).toBe(
            'Las 212 personas tienen su línea de reporte completa.',
        );
        expect(orderText(1)).toBe(
            'La única persona activa tiene su línea de reporte completa.',
        );
    });
});
