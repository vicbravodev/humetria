import { describe, expect, it } from 'vite-plus/test';

import {
    inputsLine,
    reportsLine,
    routeIntro,
    routeStages,
} from '@/lib/seating/route';
import type { RouteProgress, RouteRow } from '@/types';

const route: RouteProgress = {
    applies: true,
    done: [true, true, false, true, true, false],
    status: 'evalúa a 3 personas',
    answered: true,
};

function row(numeral: string, stage: number): RouteRow {
    return {
        numeral,
        title: numeral,
        detail: null,
        state: 'missing',
        stage,
        href: null,
        action: null,
    };
}

describe('routeStages', () => {
    it('lays out the six stages of Ley Silla with their rows and what each says once done', () => {
        const stages = routeStages(route, [row('R2', 3), row('R1', 1)]);

        expect(stages.map((stage) => stage.state)).toEqual([
            'done',
            'done',
            'current',
            'done',
            'done',
            'pending',
        ]);
        expect(stages[2]?.title).toBe(
            'Evalúa a cada persona que trabaja de pie',
        );
        expect(stages[2]?.rows.map((each) => each.numeral)).toEqual(['R2']);
        expect(stages[0]?.summary).toBe(
            'Hecho: cada puesto de pie registrado con su Figura 1',
        );
    });
});

describe('routeIntro', () => {
    it('says who works standing in how many positions, and how many stages are done', () => {
        expect(
            routeIntro('Planta Querétaro', { people: 9, positions: 3 }, route),
        ).toBe(
            'En Planta Querétaro 9 personas trabajan de pie en 3 puestos. Estas son las 6 etapas, en orden. Ya terminaste 4.',
        );
    });

    it('speaks of one person and one position, and of the first stage when none is done', () => {
        expect(
            routeIntro(
                'Tienda Centro',
                { people: 1, positions: 1 },
                {
                    ...route,
                    done: [false, false, false, false, false, false],
                },
            ),
        ).toBe(
            'En Tienda Centro 1 persona trabaja de pie en 1 puesto. Estas son las 6 etapas, en orden. Empieza por la primera.',
        );
    });
});

describe('reportsLine', () => {
    it('says how many seat reports wait and since when', () => {
        expect(
            reportsLine({
                count: 1,
                since: '2026-10-10',
                waiting: 'hace 3 semanas',
                href: '/r',
            }),
        ).toBe(
            'Una persona avisó de un asiento dañado. Espera respuesta desde hace 3 semanas.',
        );
        expect(
            reportsLine({
                count: 2,
                since: '2026-10-10',
                waiting: 'hace 3 semanas',
                href: '/r',
            }),
        ).toBe(
            '2 avisos de asientos dañados siguen abiertos. El más antiguo espera respuesta desde hace 3 semanas.',
        );
    });
});

describe('inputsLine', () => {
    it('says how many people answered their two questions', () => {
        expect(inputsLine({ answered: 6, people: 9 })).toBe(
            '6 de 9 ya contestaron sus 2 preguntas sobre calzado y molestias.',
        );
        expect(inputsLine({ answered: 1, people: 1 })).toBe(
            '1 de 1 ya contestó sus 2 preguntas sobre calzado y molestias.',
        );
    });
});
