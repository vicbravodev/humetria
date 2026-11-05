import { describe, expect, it } from 'vite-plus/test';

import {
    accidentsLine,
    inspectionReach,
    routeIntro,
    routeStages,
    stageDone,
    stageHref,
    stageOneTasks,
} from '@/lib/telework/route';
import type { RouteRow } from '@/types';

function row(
    numeral: string,
    stage: number,
    state: RouteRow['state'],
): RouteRow {
    return {
        numeral,
        title: numeral,
        detail: null,
        state,
        stage,
        href: null,
        action: null,
    };
}

const rows: RouteRow[] = [
    { ...row('5.1', 2, 'done'), href: '/personas' },
    { ...row('5.2', 1, 'missing'), href: '/documentos' },
    row('5.3', 1, 'done'),
    row('5.6', 1, 'missing'),
    row('5.11', 5, 'not_applicable'),
    row('5.7', 4, 'done'),
];

describe('routeStages', () => {
    const stages = routeStages(
        {
            applies: true,
            done: [false, true, false, true, false, false],
            status: 'define las reglas',
            answered: true,
        },
        rows,
    );

    it('marks the first stage left as the current one and the rest by their state', () => {
        expect(stages.map((stage) => stage.state)).toEqual([
            'current',
            'done',
            'pending',
            'done',
            'pending',
            'pending',
        ]);
        expect(stages[0]?.title).toBe('Define las reglas del teletrabajo');
        expect(stages[1]?.summary).toBe(
            'Hecho: cada persona con su lugar convenido',
        );
    });

    it('puts each row in its stage', () => {
        expect(stages[0]?.rows.map((each) => each.numeral)).toEqual([
            '5.2',
            '5.3',
            '5.6',
        ]);
        expect(stages[5]?.rows).toEqual([]);
    });

    it('opens a stage at the first row the person may open', () => {
        expect(stages[0] === undefined ? null : stageHref(stages[0])).toBe(
            '/documentos',
        );
        expect(
            stages[3] === undefined ? null : stageHref(stages[3]),
        ).toBeNull();
    });

    it('folds a done stage into its summary and the first link of its rows', () => {
        expect(stages[1] === undefined ? null : stageDone(stages[1])).toEqual({
            summary: 'Hecho: cada persona con su lugar convenido',
            href: '/personas',
            action: 'Ver',
        });
    });
});

describe('inspectionReach', () => {
    it('counts what can be shown of what applies, and where the stage takes it', () => {
        const [first] = routeStages(
            {
                applies: true,
                done: [false, false, false, false, false, false],
                status: '',
                answered: true,
            },
            rows,
        );

        expect(inspectionReach(rows, first)).toEqual({
            ready: 3,
            applicable: 5,
            afterStage: 5,
        });
        expect(inspectionReach(rows, undefined)).toEqual({
            ready: 3,
            applicable: 5,
            afterStage: 3,
        });
    });
});

describe('stageOneTasks', () => {
    it('keeps the policy apart from its supporting documents', () => {
        const tasks = stageOneTasks(rows.filter((each) => each.stage === 1));

        expect(tasks.policy?.numeral).toBe('5.2');
        expect(tasks.documents.map((each) => each.numeral)).toEqual([
            '5.3',
            '5.6',
        ]);
        expect(tasks.ready).toBe(1);
    });
});

describe('accidentsLine', () => {
    it('names a lone person and the day', () => {
        expect(
            accidentsLine({
                person: 'Hugo Saiz',
                count: 1,
                since: '2026-02-27',
                waiting: 'hace 8 meses',
                href: '/a',
            }),
        ).toBe(
            'Hugo Saiz avisó un accidente el 27 feb. Espera respuesta desde hace 8 meses.',
        );
    });

    it('counts several people', () => {
        expect(
            accidentsLine({
                person: null,
                count: 3,
                since: '2026-02-27',
                waiting: 'hace 2 días',
                href: '/a',
            }),
        ).toBe(
            '3 personas avisaron un accidente. El más antiguo espera respuesta desde hace 2 días.',
        );
    });
});

describe('routeIntro', () => {
    const route = {
        applies: true,
        done: [false, true, false, true, false, false],
        status: '',
        answered: true,
    };

    it('says who teleworks and how many stages are done', () => {
        expect(routeIntro('Planta Querétaro', 3, route)).toBe(
            'Planta Querétaro tiene 3 personas que trabajan desde casa más del 40 % del tiempo. Estas son las 6 etapas que pide la NOM-037, en orden. Ya terminaste 2.',
        );
    });

    it('speaks of one person, and of starting when nothing is done', () => {
        expect(
            routeIntro('Oficinas', 1, {
                ...route,
                done: [false, false, false, false, false, false],
            }),
        ).toBe(
            'Oficinas tiene 1 persona que trabaja desde casa más del 40 % del tiempo. Estas son las 6 etapas que pide la NOM-037, en orden. Empieza por la primera.',
        );
    });
});
