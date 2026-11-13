import { countOf, plural } from '@/lib/plural';

/** How many active people lack each thing the calculator needs; one person can lack several, so they can add up to more than who is left out. */
export type GapCauses = {
    without_grade: number;
    without_hire_date: number;
    without_group: number;
};

/** "1 sin categoría y nivel · 3 sin grupo (sindicalizado o de confianza)": the real cause of who is left out of the cost, not one guess. */
export function gapCauses(causes: GapCauses): string {
    return [
        causes.without_grade > 0 &&
            `${causes.without_grade} sin categoría y nivel`,
        causes.without_hire_date > 0 &&
            `${causes.without_hire_date} sin fecha de ingreso`,
        causes.without_group > 0 &&
            `${causes.without_group} sin grupo (sindicalizado o de confianza)`,
    ]
        .filter((part) => part !== false)
        .join(' · ');
}

/** Where the data of the contract stands, as the server counts it (boards V4-Comp-Inicio and V4-Comp-Datos). */
export type ContractStatus = {
    roster: { people: number; missing: number } & GapCauses;
    tabulator: {
        grades: number;
        without_salary: number;
        /** Categories whose salary in force today is under the minimum wage. */
        below_minimum: number;
    };
    benefits: { active: number };
    overrides: { people: number };
    risk_premium: { set: boolean };
    /** The one notice of accuracy (C6): how many people the cost covers and how many data would make it exact. */
    accuracy: { costed: number; total: number; missing: number };
};

export type StatusKey =
    | 'roster'
    | 'tabulator'
    | 'benefits'
    | 'overrides'
    | 'risk_premium';

/** One datum of the contract: what it is, how much there is and what it lacks; `problem` when it keeps the cost from being exact. */
export type StatusItem = {
    key: StatusKey;
    label: string;
    value: string;
    note: string;
    problem: boolean;
};

function rosterItem(
    { people, missing, ...causes }: ContractStatus['roster'],
    union: boolean,
): StatusItem {
    return {
        key: 'roster',
        label: 'Padrón',
        value: countOf(
            people,
            union ? 'agremiado' : 'persona',
            union ? 'agremiados' : 'personas',
        ),
        note: missing === 0 ? 'Al día' : gapCauses(causes),
        problem: missing > 0,
    };
}

function tabulatorItem({
    grades,
    without_salary,
    below_minimum,
}: ContractStatus['tabulator']): StatusItem {
    const problems = [
        without_salary > 0 &&
            `${countOf(without_salary, 'categoría', 'categorías')} sin salario`,
        below_minimum > 0 &&
            `${countOf(below_minimum, 'categoría', 'categorías')} bajo el salario mínimo`,
    ].filter((problem) => problem !== false);

    return {
        key: 'tabulator',
        label: 'Tabulador',
        value: countOf(grades, 'categoría', 'categorías'),
        note: problems.length === 0 ? 'Al día' : problems.join(' · '),
        problem: without_salary > 0 || below_minimum > 0,
    };
}

/** The data of the contract in the order of the boards; the individual salaries only where they are listed (`withOverrides`). */
export function statusItems(
    status: ContractStatus,
    union: boolean,
    withOverrides: boolean,
): StatusItem[] {
    const premium = status.risk_premium.set;
    const items: StatusItem[] = [
        rosterItem(status.roster, union),
        tabulatorItem(status.tabulator),
        {
            key: 'benefits',
            label: 'Prestaciones',
            value: `${status.benefits.active} ${plural(status.benefits.active, 'activa', 'activas')}`,
            note: status.benefits.active === 0 ? 'Revisa las de ley' : 'Al día',
            problem: false,
        },
        {
            key: 'risk_premium',
            label: 'Prima de riesgo de trabajo',
            value: premium ? 'Capturada' : 'Falta',
            note: premium
                ? 'Al día'
                : 'Sin ella no se calcula esa cuota del IMSS',
            problem: !premium,
        },
    ];
    const overrides: StatusItem = {
        key: 'overrides',
        label: 'Sueldos individuales',
        value: countOf(status.overrides.people, 'persona', 'personas'),
        note: 'Quien gana distinto a su categoría',
        problem: false,
    };

    return withOverrides
        ? [items[1], items[2], overrides, items[3], items[0]].filter(
              (item) => item !== undefined,
          )
        : items;
}

/** "El cálculo cubre 90 de 91 personas. Con 2 datos más, el número es exacto." (C6) */
export function accuracySentence({
    costed,
    total,
    missing,
}: ContractStatus['accuracy']): string {
    const covers = `El cálculo cubre ${costed} de ${countOf(total, 'persona', 'personas')}.`;

    return missing === 0
        ? `${covers} Tienes todos los datos: el número es exacto.`
        : `${covers} Con ${countOf(missing, 'dato', 'datos')} más, el número es exacto.`;
}

/** "sin retroactivo", "1 mes de retroactivo", "2 meses de retroactivo". */
export function retroText(months: number): string {
    return months === 0
        ? 'sin retroactivo'
        : `${countOf(months, 'mes', 'meses')} de retroactivo`;
}

/** The width of a scenario's bar against the dearest on the page, 0 to 100. */
export function barPercent(cents: number, dearest: number): number {
    return dearest > 0 && cents > 0
        ? Math.round((cents / dearest) * 1000) / 10
        : 0;
}

/** The most scenarios the comparison takes at once. */
const COMPARED = 4;

/** Whether «Comparar» can take every active scenario at once: two to four, all on this page. */
export function comparesAll(active: number, onPage: number): boolean {
    return active >= 2 && active <= COMPARED && onPage === active;
}

/** "Hoy pagas $19,221,648 al año en nómina y cargas de 90 personas." — of the company, when the account is a union. */
export function todaySentence(
    today: { people: number; cost_text: string },
    union: boolean,
): string {
    const people = countOf(
        today.people,
        union ? 'agremiado' : 'persona',
        union ? 'agremiados' : 'personas',
    );

    return `${union ? 'Hoy la empresa paga' : 'Hoy pagas'} ${today.cost_text} al año en nómina y cargas de ${people}.`;
}
