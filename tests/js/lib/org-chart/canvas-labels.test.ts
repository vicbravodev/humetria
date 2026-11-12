import { describe, expect, it } from 'vite-plus/test';

import {
    DEPTHS,
    depthLabel,
    factTexts,
    headsRibbon,
    nodeLabel,
    pillText,
    undoText,
} from '@/lib/org-chart/canvas-labels';
import type { OrgNode } from '@/types';

const node = (extra: Partial<OrgNode> = {}): OrgNode => ({
    id: 'a',
    name: 'Esther Alaniz Segundo',
    job_title: 'Almacenista',
    area: 'Operaciones',
    work_center: 'Oficina',
    active: true,
    direct_reports: 3,
    total_reports: 78,
    flag: null,
    matches: true,
    manager_inactive: false,
    absent_today: false,
    ...extra,
});

describe('nodeLabel', () => {
    it('says who they are, their job and the size of their team', () => {
        expect(nodeLabel(node())).toBe(
            'Esther Alaniz Segundo, Almacenista, 3 directos, 78 en total',
        );
    });

    it('leaves out what is not known, and says when nobody reports to them', () => {
        expect(
            nodeLabel(
                node({ job_title: '', direct_reports: 0, total_reports: 0 }),
            ),
        ).toBe('Esther Alaniz Segundo, sin equipo');
        expect(nodeLabel(node({ direct_reports: 1, total_reports: 1 }))).toBe(
            'Esther Alaniz Segundo, Almacenista, 1 directo, 1 en total',
        );
    });
});

describe('pillText', () => {
    it('shows a closed branch with what is under it, and an open one with its direct reports', () => {
        expect(
            pillText(node({ direct_reports: 4, total_reports: 30 }), false),
        ).toBe('+4 · 30 en total');
        expect(pillText(node({ direct_reports: 3 }), true)).toBe('3 directos');
        expect(pillText(node({ direct_reports: 1 }), true)).toBe('1 directo');
    });
});

describe('factTexts', () => {
    it('writes the three facts of the header', () => {
        expect(factTexts({ people: 212, levels: 4, withManager: 209 })).toEqual(
            ['212 personas activas', '4 niveles', '209 con jefe directo'],
        );
        expect(factTexts({ people: 1, levels: 1, withManager: 0 })).toEqual([
            '1 persona activa',
            '1 nivel',
            'Ninguna con jefe directo',
        ]);
    });
});

describe('headsRibbon', () => {
    it('says how many people have nobody above them, and that one is normal', () => {
        expect(headsRibbon(3, 0)).toBe(
            '3 personas sin jefe: normalmente hay una',
        );
    });

    it('adds whoever has no place in the chart yet', () => {
        expect(headsRibbon(3, 2)).toBe(
            '3 personas sin jefe: normalmente hay una. Otras 2 no tienen jefe ni equipo y no se dibujan',
        );
        expect(headsRibbon(1, 1)).toBe(
            'Otra persona no tiene jefe ni equipo y no se dibuja',
        );
        expect(headsRibbon(1, 3)).toBe(
            'Otras 3 personas no tienen jefe ni equipo y no se dibujan',
        );
    });
});

describe('depthLabel', () => {
    it('names how many levels are shown', () => {
        expect(DEPTHS).toEqual([1, 2, 3, 4, Infinity]);
        expect(depthLabel(1)).toBe('Mostrar: 1 nivel');
        expect(depthLabel(3)).toBe('Mostrar: 3 niveles');
        expect(depthLabel(Infinity)).toBe('Mostrar: todos');
    });
});

describe('undoText', () => {
    it('says who reports to whom now, or that they are left with nobody above', () => {
        expect(undoText('Tomás Beltrán', 'Ander Noriega')).toBe(
            'Tomás Beltrán ahora reporta a Ander Noriega',
        );
        expect(undoText('Tomás Beltrán', null)).toBe(
            'Tomás Beltrán se quedó sin jefe directo',
        );
    });
});
