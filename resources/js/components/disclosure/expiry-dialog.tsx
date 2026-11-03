import DatePicker from '@/components/humetria/date-picker';
import FormDialog from '@/components/humetria/form-dialog';
import InputError from '@/components/humetria/input-error';
import { Label } from '@/components/ui/label';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { expiry } from '@/routes/tenant/disclosure/page';
import type { PageLinkData } from '@/types';

/**
 * The last day the link opens. The page is permanent: leaving the day empty means it never expires. From tomorrow and no
 * further than the longest term the server accepts.
 */
export default function ExpiryDialog({
    open,
    onOpenChange,
    link,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    link: PageLinkData;
}) {
    const tenantSlug = useTenantSlug();

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title="Vencimiento del enlace"
            description="Después de ese día el enlace ya no abre. Sin día, nunca vence. Te avisamos en el resumen 30 días antes."
            form={expiry.form(tenantSlug)}
            submit="Guardar"
        >
            {(errors) => (
                <div className="grid gap-2">
                    <Label htmlFor="expiry-day">
                        Último día que abre (déjalo vacío para que no venza)
                    </Label>
                    <DatePicker
                        id="expiry-day"
                        name="expires_on"
                        defaultValue={link.expiresDay ?? ''}
                        invalid={errors['expires_on'] !== undefined}
                    />
                    <InputError message={errors['expires_on']} />
                </div>
            )}
        </FormDialog>
    );
}
