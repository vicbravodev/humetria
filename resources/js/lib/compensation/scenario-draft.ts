import { countOf } from '@/lib/plural';
import type { AssumptionField } from '@/types';

/** How a scenario raises the salary: a percent of it, or a fixed amount a month in pesos. */
export type RaiseUnit = 'percent' | 'amount';

/** What the server costs of the scenario being typed (decision A12), its money already written. */
export type ScenarioPreview = {
    people: number;
    groups: { unionized: number; trusted: number };
    /** People who cannot be costed: no category or level, or no salary for theirs. */
    left_out: number;
    first_year_cents: number;
    first_year_text: string;
    retroactive_text: string;
    retroactive_month_text: string;
    recurring_text: string;
    per_person_month_text: string;
    /** The first year over what is paid today, as a percent: "6.4". */
    over_today_text: string;
    benefits: { benefit_id: string; yearly_text: string }[];
};

/** Another saved scenario of the account, with what its first year costs. */
export type OtherScenario = {
    id: string;
    name: string;
    first_year_cents: number;
    first_year_text: string;
};

export type CompareBar = {
    key: string;
    name: string;
    text: string;
    /** Width of the bar against the dearest, 0 to 100. */
    percent: number;
    current: boolean;
};

const STEPS: Record<RaiseUnit, { step: number; max: number }> = {
    percent: { step: 0.5, max: 100 },
    amount: { step: 50, max: 100_000 },
};

/** The raise one step up or down: half a point, or 50 pesos a month, within its range. */
export function stepRaise(
    text: string,
    direction: 1 | -1,
    unit: RaiseUnit,
): string {
    const { step, max } = STEPS[unit];
    const current = Number(text.replace(',', '.'));
    const start = Number.isFinite(current) && text.trim() !== '' ? current : 0;
    const next = Math.min(max, Math.max(0, start + direction * step));

    return String(Math.round(next * 100) / 100);
}

export function stepMonths(
    months: number,
    direction: 1 | -1,
    limit: number,
): number {
    return Math.min(limit, Math.max(0, months + direction));
}

/** "Sin retroactivo", "1 mes", "12 meses". */
export function monthsLabel(months: number): string {
    return months === 0 ? 'Sin retroactivo' : countOf(months, 'mes', 'meses');
}

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function isBenefitCost(
    value: unknown,
): value is ScenarioPreview['benefits'][number] {
    return (
        isRecord(value) &&
        typeof value['benefit_id'] === 'string' &&
        typeof value['yearly_text'] === 'string'
    );
}

function texts(value: Record<string, unknown>, keys: string[]): boolean {
    return keys.every((key) => typeof value[key] === 'string');
}

/** The server's answer, or null when it is not one. */
export function parsePreview(value: unknown): ScenarioPreview | null {
    if (
        !isRecord(value) ||
        !isRecord(value['groups']) ||
        !Array.isArray(value['benefits'])
    ) {
        return null;
    }

    const { people, left_out, first_year_cents, groups, benefits } = value;
    const numbers = [
        people,
        left_out,
        first_year_cents,
        groups['unionized'],
        groups['trusted'],
    ];
    const textKeys = [
        'first_year_text',
        'retroactive_text',
        'retroactive_month_text',
        'recurring_text',
        'per_person_month_text',
        'over_today_text',
    ];

    if (
        !numbers.every((number) => typeof number === 'number') ||
        !texts(value, textKeys) ||
        !benefits.every(isBenefitCost)
    ) {
        return null;
    }

    return {
        people: Number(people),
        groups: {
            unionized: Number(groups['unionized']),
            trusted: Number(groups['trusted']),
        },
        left_out: Number(left_out),
        first_year_cents: Number(first_year_cents),
        first_year_text: String(value['first_year_text']),
        retroactive_text: String(value['retroactive_text']),
        retroactive_month_text: String(value['retroactive_month_text']),
        recurring_text: String(value['recurring_text']),
        per_person_month_text: String(value['per_person_month_text']),
        over_today_text: String(value['over_today_text']),
        benefits: benefits.filter(isBenefitCost),
    };
}

/** The first message of each field of a validation answer. */
export function parsePreviewErrors(value: unknown): Record<string, string> {
    const errors: Record<string, string> = {};

    if (!isRecord(value) || !isRecord(value['errors'])) {
        return errors;
    }

    for (const [field, messages] of Object.entries(value['errors'])) {
        if (Array.isArray(messages) && typeof messages[0] === 'string') {
            errors[field] = messages[0];
        }
    }

    return errors;
}

/** «Frente a tus otros escenarios»: from the cheapest, each bar against the dearest; nothing without others. */
export function compareBars(
    others: OtherScenario[],
    current: { cents: number; text: string },
): CompareBar[] {
    if (others.length === 0) {
        return [];
    }

    const rows = [
        ...others.map((other) => ({
            key: other.id,
            name: other.name,
            text: other.first_year_text,
            cents: other.first_year_cents,
            current: false,
        })),
        {
            key: 'current',
            name: 'Este escenario',
            text: current.text,
            cents: current.cents,
            current: true,
        },
    ].sort((a, b) => a.cents - b.cents);
    const dearest = Math.max(...rows.map((row) => row.cents));

    return rows.map(({ cents, ...row }) => ({
        ...row,
        percent:
            dearest > 0 && cents > 0
                ? Math.round((cents / dearest) * 1000) / 10
                : 0,
    }));
}

/** "Inflación 3 % · rotación 0 % · …". */
export function assumptionsSummary(assumptions: AssumptionField[]): string {
    return assumptions
        .map(
            ({ label, value }, index) =>
                `${index === 0 ? label : label.toLowerCase()} ${value} %`,
        )
        .join(' · ');
}

/** "+$108,000 al año", or "-$5,000 al año" for a change that saves. */
export function signedYearly(text: string): string {
    return `${text.startsWith('-') ? '' : '+'}${text} al año`;
}

/** "1 persona queda fuera porque no tiene categoría o salario." */
export function leftOutText(people: number): string {
    return people === 1
        ? '1 persona queda fuera porque no tiene categoría o salario.'
        : `${people} personas quedan fuera porque no tienen categoría o salario.`;
}

/** The numbers of the live cost, or a dash each while the first answer arrives. */
export function headlineTexts(preview: ScenarioPreview | null): {
    first: string;
    recurring: string;
    perPerson: string;
} {
    return preview === null
        ? { first: '—', recurring: '—', perPerson: '—' }
        : {
              first: preview.first_year_text,
              recurring: preview.recurring_text,
              perPerson: preview.per_person_month_text,
          };
}
