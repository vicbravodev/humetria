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
];

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
const CHANNEL: Choice<PriceProfile>[] = [
    { value: 'channel_compliance', label: 'Canal Cumplimiento' },
    { value: 'channel_integral', label: 'Canal Integral' },
];

/** How a plan can be priced for the account that pays it: the channel rates are a consultant's alone. */
export function profileChoices(kind: AccountKind): Choice<PriceProfile>[] {
    return kind === 'consultant'
        ? [DIRECT, ...CHANNEL, NEGOTIATED]
        : [DIRECT, NEGOTIATED];
}

/** The plan a channel profile goes with; null for a profile that takes any. */
export function planOf(profile: PriceProfile): Plan | null {
    if (profile === 'channel_compliance') {
        return 'cumplimiento';
    }

    return profile === 'channel_integral' ? 'integral' : null;
}

/** The plan after choosing a profile: a channel rate brings its own plan, any other keeps the one chosen. */
export function planAfterProfile(plan: Plan, profile: PriceProfile): Plan {
    return planOf(profile) ?? plan;
}

/** The profile after choosing another plan: a channel rate only fits its own plan, so it falls back to the direct one. */
export function profileAfterPlan(profile: PriceProfile): PriceProfile {
    return planOf(profile) === null ? profile : 'direct';
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
