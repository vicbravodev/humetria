import type { CompanyRoleKey, TenantRole } from '@/types/tenancy';

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

// A role of the company's own allows whatever its owner switched on.
const OWN_ROLE_SUMMARY = 'entra con los permisos que se le asignaron';

function isTemplate(role: CompanyRoleKey): role is TenantRole {
    return Object.hasOwn(SUMMARIES, role);
}

/** What a role in a company does, to follow its name: "RH · opera evaluaciones, centros y empleados". */
export function roleSummary(role: CompanyRoleKey, union = false): string {
    if (!isTemplate(role)) {
        return OWN_ROLE_SUMMARY;
    }

    return (union ? UNION_SUMMARIES[role] : undefined) ?? SUMMARIES[role];
}
