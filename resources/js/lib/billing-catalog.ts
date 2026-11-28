import { countOf, plural } from '@/lib/plural';
import type { CatalogPlan } from '@/types';

// The names the company menu gives each module (Tenancy's Module enum): the console says the same thing the company sees.
const MODULE_LABELS: Record<string, string> = {
    attendance: 'Asistencia',
    vacations: 'Vacaciones',
    org_chart: 'Organigrama',
    nom035: 'NOM-035',
    nom037: 'NOM-037',
    seating: 'Ley Silla',
    complaints: 'Quejas',
    pulse: 'Pulso',
    climate: 'Clima',
    compensation: 'Compensación',
    sso: 'Inicio de sesión único',
};

/** The name of a module of the catalog; its key when it is one this list does not know yet. */
export function moduleLabel(key: string): string {
    return MODULE_LABELS[key] ?? key;
}

/** The price of a plan as its card says it: a rate per person with its minimum, or a union's flat fee. */
export function planPrice(plan: CatalogPlan): {
    amount: string;
    per: string;
    note: string;
} {
    return plan.flat !== null
        ? { amount: plan.flat, per: 'al mes', note: 'Cuota fija' }
        : {
              amount: plan.rate ?? '—',
              per: 'por persona al mes',
              note: `Mínimo ${plan.minimum ?? '—'} al mes`,
          };
}

/** The search of the negotiated prices as the address carries it: without it when it is empty. */
export function plansQuery(filters: { q: string }): { q?: string } {
    const q = filters.q.trim();

    return q === '' ? {} : { q };
}

/** How many companies are on a plan and how many of them renew in the next 90 days. */
export function planReach(plan: CatalogPlan): string {
    if (plan.companies === 0) {
        return 'Ninguna empresa';
    }

    const companies = countOf(plan.companies, 'empresa', 'empresas');

    return plan.renewing === 0
        ? companies
        : `${companies} · ${plan.renewing} ${plural(plan.renewing, 'renueva', 'renuevan')} en 90 días`;
}

/** The plan with the most companies on it («El más usado»); null while no company is on any. */
export function mostUsed(plans: CatalogPlan[]): string | null {
    const top = plans.reduce<CatalogPlan | null>(
        (best, plan) => (plan.companies > (best?.companies ?? 0) ? plan : best),
        null,
    );

    return top?.key ?? null;
}
