import type {
    AccountCompanyRow,
    AccountKind,
    Plan,
    PriceProfile,
    SubscriptionStatus,
    SubscriptionView,
} from '@/types';

/** A charge made once or every period. */
export type ChargeKind = 'one_time' | 'recurring';

type Choice<V extends string> = { value: V; label: string };

export const PLANS: Choice<Plan>[] = [
    { value: 'jornada', label: 'Jornada' },
    { value: 'cumplimiento', label: 'Cumplimiento' },
    { value: 'integral', label: 'Integral' },
    { value: 'enterprise', label: 'Enterprise' },
];

const UNION_PLAN: Choice<Plan> = { value: 'sindicato', label: 'Sindicato' };

/** The plans a company can be given: a union has its own and nothing else; a company has the four and never that one. */
export function planChoices(union: boolean): Choice<Plan>[] {
    return union ? [UNION_PLAN] : PLANS;
}

export const STATUSES: Choice<SubscriptionStatus>[] = [
    { value: 'trial', label: 'En prueba' },
    { value: 'active', label: 'Activa' },
    { value: 'past_due', label: 'Con saldo vencido' },
    { value: 'cancel_scheduled', label: 'Cancelación programada' },
    { value: 'ended', label: 'Terminada' },
];

const DIRECT: Choice<PriceProfile> = { value: 'direct', label: 'Directo' };
const NEGOTIATED: Choice<PriceProfile> = {
    value: 'negotiated',
    label: 'Precio negociado',
};
const UNION_FEE: Choice<PriceProfile> = {
    value: 'union',
    label: 'Cuota fija de sindicato',
};
const CHANNEL: Choice<PriceProfile>[] = [
    { value: 'channel_compliance', label: 'Canal Cumplimiento' },
    { value: 'channel_integral', label: 'Canal Integral' },
];

/** How a plan can be priced: a union pays its flat fee or a price agreed; a company, by the account that pays it, where the channel rates are a consultant's alone. */
export function profileChoices(
    kind: AccountKind,
    union = false,
): Choice<PriceProfile>[] {
    if (union) {
        return [UNION_FEE, NEGOTIATED];
    }

    return kind === 'consultant'
        ? [DIRECT, ...CHANNEL, NEGOTIATED]
        : [DIRECT, NEGOTIATED];
}

/** The plan a channel profile goes with; null for a profile that takes any. */
export function planOf(profile: PriceProfile): Plan | null {
    if (profile === 'channel_compliance') {
        return 'cumplimiento';
    }

    if (profile === 'union') {
        return 'sindicato';
    }

    return profile === 'channel_integral' ? 'integral' : null;
}

/** The plan after choosing a profile: a channel rate brings its own plan, any other keeps the one chosen. */
export function planAfterProfile(plan: Plan, profile: PriceProfile): Plan {
    return planOf(profile) ?? plan;
}

/** The profile after choosing another plan: a channel rate only fits its own plan, so it falls back to the direct one; the flat fee of a union has no other plan to move to. */
export function profileAfterPlan(profile: PriceProfile): PriceProfile {
    return planOf(profile) === null || profile === 'union' ? profile : 'direct';
}

/** What the plan form starts with. */
export type PlanDefaults = {
    plan: Plan;
    profile: PriceProfile;
    status: SubscriptionStatus;
    seats: string;
    centers: string;
    startsOn: string;
    price: string;
    validUntil: string;
};

/** The form of a plan that exists: it starts from what the plan says. */
function existingDefaults(current: SubscriptionView): PlanDefaults {
    return {
        plan: current.plan,
        profile: current.profile,
        status: current.status,
        seats: String(current.seats),
        centers: String(current.centers),
        startsOn: current.starts_on,
        price: current.negotiated_price,
        validUntil: current.negotiated_valid_until,
    };
}

/**
 * The form of a company's plan starts from the plan it has; a company without one starts from a new plan for the people
 * and centers it already has (at least one of each), beginning today.
 */
export function planDefaults(
    company: AccountCompanyRow,
    today: string,
): PlanDefaults {
    if (company.subscription === null && company.union) {
        // A union has its own plan and fee, and nobody counted: the fee is the same for everyone.
        return {
            plan: 'sindicato',
            profile: 'union',
            status: 'active',
            seats: '0',
            centers: '1',
            startsOn: today,
            price: '',
            validUntil: '',
        };
    }

    return company.subscription === null
        ? {
              plan: 'cumplimiento',
              profile: 'direct',
              status: 'active',
              seats: String(Math.max(company.people, 1)),
              centers: String(Math.max(company.work_centers, 1)),
              startsOn: today,
              price: '',
              validUntil: '',
          }
        : existingDefaults(company.subscription);
}
