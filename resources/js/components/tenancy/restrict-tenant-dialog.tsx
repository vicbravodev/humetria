import FormDialog from '@/components/humetria/form-dialog';
import TenantEffects from '@/components/tenancy/tenant-effects';
import TenantReasonField from '@/components/tenancy/tenant-reason-field';
import {
    type Restriction,
    restrictionCopy,
    restrictionEffects,
} from '@/lib/tenant-restriction';
import { restrict } from '@/routes/companies';
import type { TenantRow } from '@/types';

/**
 * Puts one company in read only, suspended or archived: shows what will happen, asks the reason, and applies it to this
 * company alone. Its data is kept.
 */
export default function RestrictTenantDialog({
    tenant,
    restriction,
    open,
    onOpenChange,
}: {
    tenant: TenantRow;
    restriction: Restriction;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const copy = restrictionCopy(restriction, tenant.trade_name);

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={copy.title}
            description="Revisa qué cambia antes de confirmar. Solo afecta a esta empresa."
            form={restrict.form(tenant.id)}
            submit={copy.submit}
            tone={copy.destructive ? 'destructive' : 'default'}
        >
            {(errors) => (
                <>
                    <input
                        type="hidden"
                        name="restriction"
                        value={restriction}
                    />
                    <TenantEffects
                        effects={restrictionEffects(
                            restriction,
                            tenant.uses_attendance,
                        )}
                    />
                    <TenantReasonField error={errors['reason']} />
                </>
            )}
        </FormDialog>
    );
}
