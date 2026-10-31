import type { TenantRole } from '@/types/tenancy';

const SUMMARIES: Record<TenantRole, string> = {
    owner: 'administra la cuenta, sus usuarios y evaluaciones',
    hr: 'opera evaluaciones, centros y empleados',
    viewer: 've resultados e informes',
    committee: 'revisa y valida los lugares de teletrabajo',
};

/** What a role in a company does, to follow its name: "RH · opera evaluaciones, centros y empleados". */
export function roleSummary(role: TenantRole): string {
    return SUMMARIES[role];
}
