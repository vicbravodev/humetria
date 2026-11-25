import CountTabs from '@/components/humetria/count-tabs';
import { auditQuery } from '@/lib/audit';
import { index } from '@/routes/audit';
import type { AuditCounts, AuditFilters } from '@/types';

const VIEWS: { kind: keyof AuditCounts; label: string }[] = [
    { kind: 'all', label: 'Todo' },
    { kind: 'support', label: 'Acceso de soporte' },
    { kind: 'billing', label: 'Cobros y planes' },
    { kind: 'results', label: 'Resultados vistos o exportados' },
    { kind: 'roles', label: 'Roles y permisos' },
];

/** The views of the trail by what matters most, each with how many entries it holds; the other filters carry over. */
export default function AuditTabs({
    filters,
    counts,
}: {
    filters: AuditFilters;
    counts: AuditCounts;
}) {
    return (
        <CountTabs
            label="Tipo de registro"
            tabs={VIEWS.map(({ kind, label }) => {
                const value = kind === 'all' ? '' : kind;

                return {
                    label,
                    count: counts[kind],
                    active: filters.kind === value,
                    href: index.url({
                        query: auditQuery({ ...filters, kind: value }),
                    }),
                };
            })}
        />
    );
}
