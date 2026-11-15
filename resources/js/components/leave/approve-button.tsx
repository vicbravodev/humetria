import ConfirmDialog from '@/components/humetria/confirm-dialog';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { cn } from '@/lib/utils';
import { approve } from '@/routes/tenant/vacations/requests';

/** «Aprobar» for RH: asks once, charges the days to the periods and tells the person by email. Only one per screen is primary. */
export default function ApproveButton({
    requestId,
    name,
    primary = true,
    block = false,
}: {
    requestId: string;
    name: string;
    primary?: boolean;
    /** Full width, 44 px tall, on a phone. */
    block?: boolean;
}) {
    const tenantSlug = useTenantSlug();

    return (
        <ConfirmDialog
            trigger={
                <Button
                    variant={primary ? 'default' : 'outline'}
                    className={cn(block && 'max-sm:h-11 max-sm:w-full')}
                >
                    Aprobar
                </Button>
            }
            title={`¿Aprobar la solicitud de ${name}?`}
            description="Se descuentan los días de su saldo y se le avisa por correo."
            confirmLabel="Sí, aprobar"
            dismissLabel="Volver"
            destructive={false}
            form={approve.form({ tenant: tenantSlug, leaveRequest: requestId })}
            preserveScroll
        />
    );
}
