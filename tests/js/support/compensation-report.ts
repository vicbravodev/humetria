import type { ReportConcept, ScenarioReport } from '@/types';

/** A concept of the cascade, written as the server sends it. */
export function concept(
    { type, key, label }: Pick<ReportConcept, 'type' | 'key' | 'label'>,
    cents: number,
): ReportConcept {
    return {
        type,
        key,
        label,
        before_text: '$0.00',
        after_text: '$0.00',
        delta_text: '$0.00',
        first_year_text: '$0.00',
        first_year_cents: cents,
    };
}

function boardConcepts(): ReportConcept[] {
    return [
        concept(
            { type: 'salary', key: 'salary', label: 'Salario' },
            76_354_905,
        ),
        concept(
            { type: 'benefit', key: 'aguinaldo', label: 'Aguinaldo' },
            3_137_899,
        ),
        concept(
            {
                type: 'benefit',
                key: 'vacation_premium',
                label: 'Prima vacacional',
            },
            1_309_885,
        ),
        concept(
            {
                type: 'contribution',
                key: 'imss_fixed',
                label: 'IMSS: cuota fija de enfermedad y maternidad',
            },
            0,
        ),
        concept(
            {
                type: 'contribution',
                key: 'imss_surplus',
                label: 'IMSS: excedente de enfermedad y maternidad',
            },
            788_820,
        ),
        concept(
            {
                type: 'contribution',
                key: 'imss_pensioners',
                label: 'IMSS: gastos médicos de pensionados',
            },
            848_552,
        ),
        concept(
            {
                type: 'contribution',
                key: 'imss_cash',
                label: 'IMSS: prestaciones en dinero',
            },
            565_721,
        ),
        concept(
            {
                type: 'contribution',
                key: 'imss_disability_life',
                label: 'IMSS: invalidez y vida',
            },
            1_414_264,
        ),
        concept(
            {
                type: 'contribution',
                key: 'imss_childcare',
                label: 'IMSS: guarderías y prestaciones sociales',
            },
            808_145,
        ),
        concept(
            { type: 'contribution', key: 'retirement', label: 'Retiro' },
            1_616_293,
        ),
        concept(
            {
                type: 'contribution',
                key: 'severance_old_age',
                label: 'Cesantía en edad avanzada y vejez',
            },
            9_058_295,
        ),
        concept(
            { type: 'contribution', key: 'infonavit', label: 'Infonavit' },
            4_040_750,
        ),
        concept(
            {
                type: 'contribution',
                key: 'payroll_tax',
                label: 'Impuesto sobre nómina',
            },
            2_424_063,
        ),
    ];
}

function boardRaises(): ScenarioReport['raises'] {
    return [
        {
            label: 'Operador · B',
            group: 'unionized',
            people: 22,
            monthly_raise_cents: 55_100,
        },
        {
            label: 'Almacenista · A',
            group: 'unionized',
            people: 10,
            monthly_raise_cents: 51_500,
        },
        {
            label: 'Operador · A',
            group: 'unionized',
            people: 36,
            monthly_raise_cents: 47_900,
        },
        {
            label: 'Supervisor · A',
            group: 'trusted',
            people: 6,
            monthly_raise_cents: 56_100,
        },
        {
            label: 'Técnico · B',
            group: 'trusted',
            people: 6,
            monthly_raise_cents: 45_500,
        },
        {
            label: 'Técnico · A',
            group: 'trusted',
            people: 10,
            monthly_raise_cents: 39_000,
        },
    ];
}

function boardProjection(): ScenarioReport['projection'] {
    return [
        {
            year: 1,
            effective_on: '2027-01-01',
            people: 90,
            before_text: '',
            after_text: '',
            delta_text: '',
            retroactive_text: '$146,207.06',
            cost_cents: 102_367_600,
        },
        {
            year: 2,
            effective_on: '2028-01-01',
            people: 91,
            before_text: '',
            after_text: '',
            delta_text: '',
            retroactive_text: null,
            cost_cents: 95_351_800,
        },
        {
            year: 3,
            effective_on: '2029-01-01',
            people: 92,
            before_text: '',
            after_text: '',
            delta_text: '',
            retroactive_text: null,
            cost_cents: 103_642_600,
        },
    ];
}

/** The report of the board V4-Comp-Reporte: Revisión 2027 · 5 %, 90 of 91 people, two retroactive months. */
export function boardReport(): ScenarioReport {
    return {
        scenario: {
            id: 'scenario-1',
            name: 'Revisión 2027 · 5 %',
            effective_on: '2027-01-01',
            horizon_years: 3,
            retroactive_months: 2,
            unionized_raise: '5',
            trusted_raise: '3',
            raise_text: '5 % sindicalizados · 3 % confianza',
            raise_unit: 'percent',
        },
        coverage: {
            total: 91,
            costed: 90,
            missing: ['E-900'],
            missing_more: 0,
            missing_without_number: 0,
        },
        headline: {
            people: 90,
            retroactive_months: 2,
            first_year_text: '$1,023,675.92',
            recurring_text: '$877,468.86',
            retroactive_text: '$146,207.06',
            before_text: '$19,221,647.92',
            after_text: '$20,099,116.78',
            first_year_cents: 102_367_592,
            recurring_cents: 87_746_886,
            retroactive_cents: 14_620_706,
            before_cents: 1_922_164_792,
            after_cents: 2_009_911_678,
        },
        concepts: boardConcepts(),
        months: [],
        cuts: { areas: [], grades: [], groups: [] },
        raises: boardRaises(),
        others: [
            {
                id: 'scenario-0',
                name: 'Revisión 2027 · 4 %',
                first_year_cents: 71_006_200,
                first_year_text: '$710,062',
            },
        ],
        projection: boardProjection(),
        seniority: {
            people: 0,
            before_text: '',
            after_text: '',
            delta_text: '',
        },
        sensitivity: {
            one_point_text: '',
            base_text: '',
            minus_text: '',
            plus_text: '',
        },
        assumptions: [],
        warnings: {
            individual: { count: 0, percent: '0', over_tenth: false },
            uncosted: [],
            uncalculated: ['IMSS: riesgo de trabajo'],
            stale: [],
        },
        person_value: {
            rows: [],
            person_total_text: '$0.00',
            company_total_text: '$0.00',
        },
        can_annex: true,
        shares: null,
    };
}
