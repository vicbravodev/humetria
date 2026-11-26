import { pesos } from '@/lib/overview';
import { countOf } from '@/lib/plural';
import type {
    CompanyDetail,
    ConsolePlan,
    TenantOwner,
    TenantPlan,
    TenantRow,
} from '@/types';

/** The seats a company uses of its plan ("96 de 150"); a company with no plan has no limit. */
export function seatsText(seats: TenantRow['seats']): string {
    return seats === null ? 'Sin plan' : `${seats.used} de ${seats.amount}`;
}

/** What part of its seats a company uses ("64 %"); nothing without a plan. */
export function seatsShare(seats: TenantRow['seats']): string {
    if (seats === null) {
        return '';
    }

    return `${seats.amount === 0 ? 0 : Math.round((seats.used / seats.amount) * 100)} %`;
}

/** What a company pays a month before VAT, as its row shows it. */
export function monthlyText(plan: TenantPlan | null): string {
    if (plan === null) {
        return '—';
    }

    return plan.monthly_cents === null
        ? 'No paga aún'
        : pesos(plan.monthly_cents);
}

/** The line under a company's name: its RFC and who owns it. */
export function ownerLine(rfc: string, owner: TenantOwner | null): string {
    return owner === null ? rfc : `${rfc} · Dueño: ${owner.name}`;
}

/** What the quick view lists of a company, label and value; what is missing, in words. */
export function peekFacts(tenant: TenantRow): [string, string][] {
    return [
        ['Estado', tenant.status_date ?? tenant.status_label],
        ['Razón social', tenant.legal_name],
        ['RFC', tenant.rfc],
        ...rowPlanFacts(tenant),
        ...peopleFacts(tenant),
    ];
}

function rowPlanFacts({ plan, seats }: TenantRow): [string, string][] {
    return [
        ['Plan', plan?.name ?? 'Sin plan'],
        ['Al mes', monthlyText(plan)],
        ['Renueva', plan?.renews_on ?? '—'],
        ['Asientos', seatsText(seats)],
    ];
}

function peopleFacts(tenant: TenantRow): [string, string][] {
    return [
        ['Dueño', tenant.owner?.name ?? 'Sin dueño'],
        ['Correo del dueño', tenant.owner?.email ?? '—'],
        ['Responsable', tenant.account_manager?.name ?? 'Sin asignar'],
        ['Último acceso', tenant.last_access ?? 'Nunca'],
    ];
}

/** The title of the dialog that gives more days of trial to the chosen companies. */
export function trialExtensionCopy(count: number): string {
    return `Extender la prueba de ${countOf(count, 'empresa', 'empresas')}`;
}

/** What the side of a company's page says of it, label and value; what is missing, in words. */
export function companyFacts(
    company: Pick<CompanyDetail, 'owner' | 'account_manager' | 'created_on'>,
    plan: ConsolePlan | null,
): [string, string][] {
    const { owner } = company;

    return [
        ['Dueño', owner?.name ?? 'Sin dueño'],
        ['Correo', owner?.email ?? '—'],
        ['Responsable', company.account_manager?.name ?? 'Sin asignar'],
        ...planFacts(plan),
        ['Alta', company.created_on],
    ];
}

function planFacts(plan: ConsolePlan | null): [string, string][] {
    return plan === null
        ? [
              ['Plan', 'Sin plan'],
              ['Renueva', '—'],
          ]
        : [
              ['Plan', plan.name],
              ['Renueva', plan.renews_on],
          ];
}
