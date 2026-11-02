import ConfirmDialog from '@/components/humetria/confirm-dialog';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { deactivate } from '@/routes/tenant/employees';
import type { EmployeeRow } from '@/types';

/** Asks for confirmation before deactivating an employee. Nothing is deleted. */
export default function DeactivateEmployeeDialog({
    employee,
    open,
    onOpenChange,
}: {
    employee: EmployeeRow;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const tenantSlug = useTenantSlug();
    return (
        <ConfirmDialog
            open={open}
            onOpenChange={onOpenChange}
            title={`¿Dar de baja a ${employee.name}?`}
            description="Ya no recibirá encuestas ni contará en su centro de trabajo. Su historia se conserva y puedes reactivarle cuando quieras."
            confirmLabel="Dar de baja"
            form={deactivate.form({
                tenant: tenantSlug,
                employee: employee.id,
            })}
            preserveScroll
        />
    );
}
