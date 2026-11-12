import { Archive, Pencil } from 'lucide-react';
import { useState } from 'react';
import ConfirmDialog from '@/components/humetria/confirm-dialog';
import RowMenu from '@/components/humetria/row-menu';
import type { RowMenuItem } from '@/components/humetria/row-menu';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { archive, edit } from '@/routes/tenant/vacations/policies';
import type { LeavePolicyCard } from '@/types';

/**
 * The menu of a policy column: «Editar» (a viewer reads it with «Ver») and, for whoever manages vacations, «Archivar», which
 * asks first and is only offered while nobody follows the policy.
 */
export default function PolicyActions({
    policy,
    canManage,
}: {
    policy: LeavePolicyCard;
    canManage: boolean;
}) {
    const tenantSlug = useTenantSlug();
    const [archiving, setArchiving] = useState(false);
    const target = { tenant: tenantSlug, policy: policy.id };
    const items: RowMenuItem[] = [
        {
            label: canManage ? 'Editar' : 'Ver',
            icon: Pencil,
            href: edit(target),
        },
        ...(canManage && policy.assigned === 0
            ? [
                  {
                      label: 'Archivar',
                      icon: Archive,
                      destructive: true,
                      onSelect: () => setArchiving(true),
                  },
              ]
            : []),
    ];

    return (
        <>
            <RowMenu label={`Acciones de ${policy.name}`} items={items} />
            <ConfirmDialog
                open={archiving}
                onOpenChange={setArchiving}
                title={`¿Archivar ${policy.name}?`}
                description="Dejará de ofrecerse. Los periodos ya otorgados conservan sus términos."
                confirmLabel="Archivar"
                form={archive.form(target)}
                preserveScroll
            />
        </>
    );
}
