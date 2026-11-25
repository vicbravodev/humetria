import { countOf } from '@/lib/plural';
import type { CompanyDetail, ConsolePlan, TenantRow } from '@/types';

/** The seats a company uses of its plan ("96 de 150"); a company with no plan has no limit. */
export function seatsText(seats: TenantRow['seats']): string {
    return seats === null ? 'Sin plan' : `${seats.used} de ${seats.amount}`;
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
