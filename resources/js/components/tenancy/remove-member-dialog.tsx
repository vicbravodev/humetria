import ConfirmDialog from '@/components/humetria/confirm-dialog';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { destroy } from '@/routes/tenant/members';
import type { MemberRow } from '@/types';

/** Asks for confirmation before taking away someone's access. Their account stays. */
export default function RemoveMemberDialog({
    member,
    open,
    onOpenChange,
}: {
    member: MemberRow;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const tenantSlug = useTenantSlug();
    return (
        <ConfirmDialog
            open={open}
            onOpenChange={onOpenChange}
            title={`¿Quitar el acceso de ${member.name}?`}
            description="Ya no podrá entrar a esta empresa. Su cuenta sigue existiendo y puedes invitarle de nuevo cuando quieras."
            confirmLabel="Quitar acceso"
            form={destroy.form({ tenant: tenantSlug, member: member.id })}
        />
    );
}
