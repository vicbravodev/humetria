import { describe, expect, it } from 'vite-plus/test';
import {
    accuracySentence,
    barPercent,
    comparesAll,
    retroText,
    statusItems,
    todaySentence,
} from '@/lib/compensation/start';
import type { ContractStatus } from '@/lib/compensation/start';

const status: ContractStatus = {
    roster: { people: 91, missing: 1 },
    tabulator: { grades: 7, without_salary: 0 },
    benefits: { active: 12 },
    overrides: { people: 3 },
    risk_premium: { set: false },
    accuracy: { costed: 90, total: 91, missing: 2 },
};

describe('statusItems', () => {
    it('says what each datum of the contract has and lacks, in the order of the start', () => {
        expect(statusItems(status, false, false)).toEqual([
            {
                key: 'roster',
                label: 'Padrón',
                value: '91 personas',
                note: '1 sin categoría ni nivel',
                problem: true,
            },
            {
                key: 'tabulator',
                label: 'Tabulador',
                value: '7 categorías',
                note: 'Al día',
                problem: false,
            },
            {
                key: 'benefits',
                label: 'Prestaciones',
                value: '12 activas',
                note: 'Al día',
                problem: false,
            },
            {
                key: 'risk_premium',
                label: 'Prima de riesgo de trabajo',
                value: 'Falta',
                note: 'Sin ella no se calcula esa cuota del IMSS',
                problem: true,
            },
        ]);
    });

    it("counts a union's members, a category without salary and a premium already captured", () => {
        const items = statusItems(
            {
                ...status,
                roster: { people: 1, missing: 0 },
                tabulator: { grades: 1, without_salary: 1 },
                benefits: { active: 1 },
                risk_premium: { set: true },
            },
            true,
            false,
        );

        expect(
            items.map((item) => [item.value, item.note, item.problem]),
        ).toEqual([
            ['1 agremiado', 'Al día', false],
            ['1 categoría', '1 categoría sin salario', true],
            ['1 activa', 'Al día', false],
            ['Capturada', 'Al día', false],
        ]);
    });

    it('lists the individual salaries among the data of the contract, the tabulator first', () => {
        expect(
            statusItems(status, false, true).map((item) => item.key),
        ).toEqual([
            'tabulator',
            'benefits',
            'overrides',
            'risk_premium',
            'roster',
        ]);
        expect(statusItems(status, false, true)[2]?.value).toBe('3 personas');
        expect(
            statusItems({ ...status, benefits: { active: 0 } }, false, false)[2]
                ?.note,
        ).toBe('Revisa las de ley');
    });
});

describe('accuracySentence', () => {
    it('says how many people the cost covers and how many data would make it exact', () => {
        expect(accuracySentence(status.accuracy)).toBe(
            'El cálculo cubre 90 de 91 personas. Con 2 datos más, el número es exacto.',
        );
        expect(accuracySentence({ costed: 1, total: 1, missing: 1 })).toBe(
            'El cálculo cubre 1 de 1 persona. Con 1 dato más, el número es exacto.',
        );
        expect(accuracySentence({ costed: 5, total: 5, missing: 0 })).toBe(
            'El cálculo cubre 5 de 5 personas. Tienes todos los datos: el número es exacto.',
        );
    });
});

describe('retroText and barPercent', () => {
    it('writes the retroactivity of a scenario', () => {
        expect(retroText(0)).toBe('sin retroactivo');
        expect(retroText(1)).toBe('1 mes de retroactivo');
        expect(retroText(2)).toBe('2 meses de retroactivo');
    });

    it('sizes a bar against the dearest, none for nothing', () => {
        expect(barPercent(50, 200)).toBe(25);
        expect(barPercent(0, 200)).toBe(0);
        expect(barPercent(5, 0)).toBe(0);
    });
});

describe('the list of scenarios', () => {
    it('compares every active scenario at once only when two to four are all on the page', () => {
        expect(comparesAll(3, 3)).toBe(true);
        expect(comparesAll(1, 1)).toBe(false);
        expect(comparesAll(5, 5)).toBe(false);
        expect(comparesAll(4, 3)).toBe(false);
    });

    it("says what today costs, the company's for a union", () => {
        expect(
            todaySentence({ people: 90, cost_text: '$19,221,648' }, false),
        ).toBe(
            'Hoy pagas $19,221,648 al año en nómina y cargas de 90 personas.',
        );
        expect(todaySentence({ people: 1, cost_text: '$1' }, true)).toBe(
            'Hoy la empresa paga $1 al año en nómina y cargas de 1 agremiado.',
        );
    });
});
