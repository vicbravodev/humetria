import FormDialog from '@/components/humetria/form-dialog';
import TenantReasonField from '@/components/tenancy/tenant-reason-field';
import { reactivate } from '@/routes/companies';
import type { TenantRow } from '@/types';

/** Lifts a company's restriction, for a reason. Its dates stay as they were: a reactivation grants no time. */
export default function ReactivateTenantDialog({
    tenant,
    open,
    onOpenChange,
}: {
    tenant: TenantRow;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={`Reactivar a ${tenant.trade_name}`}
            description="Vuelve al estado que le dan sus fechas. No reinicia la prueba ni la vigencia, y el saldo no cambia."
            form={reactivate.form(tenant.id)}
            submit="Reactivar"
        >
            {(errors) => <TenantReasonField error={errors['reason']} />}
        </FormDialog>
    );
}
