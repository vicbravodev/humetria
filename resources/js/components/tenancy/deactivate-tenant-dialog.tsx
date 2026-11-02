import ConfirmDialog from '@/components/humetria/confirm-dialog';
import { deactivate } from '@/routes/companies';
import type { TenantRow } from '@/types';

/** Asks for confirmation before blocking a company. Its data is kept. */
export default function DeactivateTenantDialog({
    tenant,
    open,
    onOpenChange,
}: {
    tenant: TenantRow;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const hasTrialLeft =
        tenant.status === 'trial' || tenant.status === 'not_started';

    return (
        <ConfirmDialog
            open={open}
            onOpenChange={onOpenChange}
            title={`¿Desactivar ${tenant.trade_name}?`}
            description={
                <>
                    Nadie de esta empresa podrá usar Humetría hasta que la
                    actives con una fecha nueva. Sus datos se conservan.
                    {hasTrialLeft && ' La prueba no se puede reanudar.'}
                </>
            }
            confirmLabel="Desactivar"
            form={deactivate.form(tenant.id)}
        />
    );
}
