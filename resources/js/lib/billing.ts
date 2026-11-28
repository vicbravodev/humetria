import { pesos } from '@/lib/overview';
import { countOf } from '@/lib/plural';
import type {
    AccountChargeRow,
    AccountFilters,
    AccountKind,
    AccountSummary,
    AgingBucket,
} from '@/types';

const KINDS: AccountKind[] = ['direct', 'consultant'];
const AGES: AgingBucket[] = ['current', '1_30', '31_60', 'over_60'];

/** A kind of account chosen in a filter; empty for anything else. */
export function kindOf(value: string): AccountKind | '' {
    return KINDS.find((kind) => kind === value) ?? '';
}

/** An age of a balance chosen in a filter; empty for anything else. */
export function ageOf(value: string): AgingBucket | '' {
    return AGES.find((age) => age === value) ?? '';
}

/** The list's search, view, type, age and month as its address carries them: without the defaults. */
export function accountsQuery(
    filters: AccountFilters,
): Partial<Record<keyof AccountFilters, string>> {
    const q = filters.q.trim();

    return {
        ...(q === '' ? {} : { q }),
        ...(filters.kind === 'all' ? {} : { kind: filters.kind }),
        ...(filters.type === '' ? {} : { type: filters.type }),
        ...(filters.age === '' ? {} : { age: filters.age }),
        ...(filters.month === '' ? {} : { month: filters.month }),
    };
}

/** The note under what came in: how it compares with the month before («+9 % contra octubre»), when there is one. */
/** The line under the aging strip: the plans that renew within 30 days and what they bring a year. */
export function renewingLine(count: number, yearly: string): string {
    return `${count} ${count === 1 ? 'plan renueva' : 'planes renuevan'} en los próximos 30 días · ${yearly} al año`;
}

export function collectedHint(change: number | null, previous: string): string {
    if (change === null) {
        return 'Pagos registrados';
    }

    return `${change > 0 ? '+' : change < 0 ? '−' : ''}${Math.abs(change)} % contra ${previous}`;
}

/** What the chosen accounts owe together, for the bar of the selection. */
export function chosenOwed(
    accounts: { id: string; outstanding_cents: number }[],
    chosen: string[],
): string {
    return pesos(
        accounts
            .filter(({ id }) => chosen.includes(id))
            .reduce((sum, { outstanding_cents }) => sum + outstanding_cents, 0),
    );
}

/** The notes under the figures of an account: what is missing a plan, what needs a quotation, how much of the total is VAT; undefined where there is nothing to say. */
export function figureHints(summary: AccountSummary) {
    return {
        companies:
            summary.without_plan === 0
                ? 'Todas con plan'
                : `${summary.without_plan} sin plan asignado`,
        subtotal:
            summary.requiring_quotation === 0
                ? undefined
                : `${countOf(summary.requiring_quotation, 'empresa requiere', 'empresas requieren')} cotización`,
        total: summary.vat === null ? undefined : `${summary.vat} de IVA`,
    };
}

export type ChargeAction = {
    kind: 'end' | 'remove';
    button: string;
    title: string;
    description: string;
    submit: string;
};

/** What the superadmin can do to an extra charge: end a recurring one that has no last day, remove a one-time one nobody billed; nothing otherwise. */
export function chargeAction(charge: AccountChargeRow): ChargeAction | null {
    if (charge.kind === 'recurring') {
        return charge.ends_on !== null
            ? null
            : {
                  kind: 'end',
                  button: 'Terminar',
                  title: 'Terminar el cargo',
                  description: `«${charge.description}» se cobra hasta el mes de su último día. Queda en la bitácora.`,
                  submit: 'Terminar cargo',
              };
    }

    return charge.billed
        ? null
        : {
              kind: 'remove',
              button: 'Quitar',
              title: 'Quitar el cargo',
              description: `«${charge.description}» todavía no se factura; al quitarlo no se cobra. Queda en la bitácora.`,
              submit: 'Quitar cargo',
          };
}
