import { describe, expect, it } from 'vite-plus/test';

import { inspectionGaps, routeIntro, routeStages } from '@/lib/nom035/route';
import type { RouteProgress, RouteRow } from '@/types';

const route: RouteProgress = {
    applies: true,
    done: [false, true, true, false, false, false],
    status: 'define tu política de prevención',
    answered: true,
};

function row(stage: number, state: RouteRow['state'] = 'missing'): RouteRow {
    return {
        numeral: `n${stage}`,
        title: `Requisito ${stage}`,
        detail: null,
        state,
        stage,
        href: null,
        action: null,
    };
}

describe('routeStages', () => {
    it('lays out the six stages of NOM-035 with their requirement and what each says once done', () => {
        const stages = routeStages(route, [row(1), row(2, 'done')]);

        expect(stages.map((stage) => stage.state)).toEqual([
            'current',
            'done',
            'done',
            'pending',
            'pending',
            'pending',
        ]);
        expect(stages[0]?.title).toBe('Define tu política de prevención');
        expect(stages[1]?.rows.map((each) => each.numeral)).toEqual(['n2']);
        expect(stages[1]?.summary).toBe('Hecho: la evaluación está vigente');
    });
});

describe('routeIntro', () => {
    it('says when the last evaluation closed and with how many, and that evaluating is only a part', () => {
        expect(
            routeIntro('Oficina', 30, {
                closedOn: '2 nov 2026',
                answered: 30,
                target: 30,
            }),
        ).toBe(
            'La evaluación de Oficina cerró el 2 nov 2026 con 30 de 30 personas. Evaluar es solo una parte: la norma también pide una política, compartir los resultados y actuar sobre los riesgos.',
        );
        expect(
            routeIntro('Oficina', 1, {
                closedOn: '2 nov 2026',
                answered: 1,
                target: 1,
            }),
        ).toContain('con 1 de 1 persona.');
        expect(
            routeIntro('Oficina', 0, {
                closedOn: '2 nov 2026',
                answered: 0,
                target: 0,
            }),
        ).toContain('cerró el 2 nov 2026. Evaluar');
    });

    it('tells a center not evaluated yet who works there and where to start', () => {
        expect(routeIntro('Bodega', 12, null)).toBe(
            'En Bodega trabajan 12 personas. Estas son las 6 etapas de la NOM-035, en orden: evaluar es solo una parte.',
        );
        expect(routeIntro('Bodega', 1, null)).toContain('trabaja 1 persona');
    });
});

describe('inspectionGaps', () => {
    it('names what an inspection would find missing', () => {
        expect(
            inspectionGaps([
                row(1),
                row(2, 'done'),
                row(4, 'not_applicable'),
                row(5, 'partial'),
            ]),
        ).toEqual(['Requisito 1', 'Requisito 5']);
    });
});
