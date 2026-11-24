import AccessSummary from '@/components/tenancy/access-summary';
import { areasFor, beyondRole, summarize } from '@/lib/permission-areas';
import { countOf } from '@/lib/plural';
import type { PermissionGroup } from '@/types';

/**
 * The invitation's «Resumen», in the words of the levels: what whoever accepts it will be able to do with the role chosen
 * (its permissions, `base`) and the extras raised past it (`own`).
 */
export default function InviteSummary({
    groups,
    role,
    own,
}: {
    groups: PermissionGroup[];
    role: { label: string; base: string[] };
    own: string[];
}) {
    const areas = areasFor(groups).map((item) => item.area);
    const raised = beyondRole(areas, own).length;

    return (
        <AccessSummary
            sentence={summarize({
                areas,
                held: [...role.base, ...own],
                subject: 'Quien acepte la invitación',
            })}
            facts={[
                { label: 'Rol', value: role.label },
                {
                    label: 'Permisos adicionales',
                    value: countOf(raised, 'área', 'áreas'),
                },
            ]}
        />
    );
}
