import type { TenantRole } from '@/types/tenancy';

const SUMMARIES: Record<TenantRole, string> = {
    owner: 'administra la cuenta, sus usuarios y evaluaciones',
    hr: 'opera evaluaciones, centros y empleados',
    viewer: 've resultados e informes',
    committee: 'revisa y valida los lugares de teletrabajo',
    complaints: 'atiende, de forma confidencial, las quejas que se le asignan',
};

// A union has no evaluations or work centers: only its padrón and compensation.
const UNION_SUMMARIES: Partial<Record<TenantRole, string>> = {
    owner: 'administra la cuenta, sus usuarios y su padrón',
    hr: 'administra el padrón y la compensación',
};

/** What a role in a company does, to follow its name: "RH · opera evaluaciones, centros y empleados". */
export function roleSummary(role: TenantRole, union = false): string {
    return (union ? UNION_SUMMARIES[role] : undefined) ?? SUMMARIES[role];
}
