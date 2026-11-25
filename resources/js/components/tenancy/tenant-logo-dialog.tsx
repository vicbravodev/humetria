import { Form } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import TenantBrandColorField from '@/components/tenancy/tenant-brand-color-field';
import TenantLogoSlot from '@/components/tenancy/tenant-logo-slot';
import { Spinner } from '@/components/ui/spinner';
import { update } from '@/routes/companies/brand';
import { destroy } from '@/routes/companies/logo';
import { destroy as destroyDark } from '@/routes/companies/logo-dark';
import type { TenantRow } from '@/types';

/**
 * The brand of a company (white label): its logo and its accent replace Humetría's on its panel, surveys, reports and
 * emails. Without a logo its pages show its name; without an accent they keep Humetría's.
 */
export default function TenantLogoDialog({
    tenant,
    open,
    onOpenChange,
}: {
    tenant: TenantRow;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const close = () => onOpenChange(false);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogTitle>Marca de la empresa</DialogTitle>
                <DialogDescription>
                    Reemplaza a Humetría en el panel, las encuestas, los
                    reportes y los correos de {tenant.trade_name}. Sin logo
                    muestran su nombre; Humetría queda solo como el sello «hecho
                    con humetría».
                </DialogDescription>
                <Form
                    {...update.form(tenant.id)}
                    onSuccess={close}
                    className="grid gap-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <TenantLogoSlot
                                id="tenant-logo"
                                name="logo"
                                label="Logo para modo claro"
                                hint="PNG, JPG o WebP de hasta 2 MB y al menos 40 px de alto. Se recortan los márgenes vacíos; mejor con fondo transparente."
                                current={tenant.logo_url}
                                remove={destroy(tenant.id)}
                                error={errors['logo']}
                                onRemoved={close}
                            />
                            <TenantLogoSlot
                                id="tenant-logo-dark"
                                name="logo_dark"
                                label="Logo para modo oscuro (opcional)"
                                hint="Con las letras claras, para que se lea sobre fondo oscuro. Sin él se usa el de modo claro."
                                current={tenant.logo_dark_url}
                                remove={destroyDark(tenant.id)}
                                onDark
                                error={errors['logo_dark']}
                                onRemoved={close}
                            />
                            <TenantBrandColorField
                                current={tenant.brand_color}
                                error={errors['brand_color']}
                            />
                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="outline">Cancelar</Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    {processing && <Spinner />}
                                    Guardar
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
