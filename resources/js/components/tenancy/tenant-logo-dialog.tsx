import { Form, Link } from '@inertiajs/react';
import { ImageUp } from 'lucide-react';
import FileDropZone from '@/components/humetria/file-drop-zone';
import InputError from '@/components/humetria/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { destroy, update } from '@/routes/companies/logo';
import type { TenantRow } from '@/types';

/** The logo a company's people see on their emails: upload one, or take it off so the emails show the name. */
export default function TenantLogoDialog({
    tenant,
    open,
    onOpenChange,
}: {
    tenant: TenantRow;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogTitle>Logo de los correos</DialogTitle>
                <DialogDescription>
                    Lo ven las personas de {tenant.trade_name} en los correos de
                    sus evaluaciones. Sin logo, los correos muestran su nombre.
                </DialogDescription>
                {tenant.logo_url !== null && (
                    <div className="flex items-center justify-between gap-4 rounded-lg border p-4">
                        <img
                            src={tenant.logo_url}
                            alt={`Logo de ${tenant.trade_name}`}
                            className="h-10 w-auto max-w-48"
                        />
                        <Button variant="ghost" size="sm" asChild>
                            <Link
                                href={destroy(tenant.id)}
                                as="button"
                                onSuccess={() => onOpenChange(false)}
                            >
                                Quitar logo
                            </Link>
                        </Button>
                    </div>
                )}
                <Form
                    {...update.form(tenant.id)}
                    onSuccess={() => onOpenChange(false)}
                    className="grid gap-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <FileDropZone
                                    id="tenant-logo"
                                    name="logo"
                                    accept="image/png,image/jpeg,image/webp"
                                    icon={ImageUp}
                                    title={
                                        tenant.logo_url === null
                                            ? 'Arrastra aquí el logo o elígelo'
                                            : 'Cambiar logo: arrástralo o elígelo'
                                    }
                                    hint="PNG, JPG o WebP de hasta 2 MB y al menos 40 px de alto. Mejor con fondo transparente."
                                    invalid={errors['logo'] !== undefined}
                                    describedBy="tenant-logo-error"
                                />
                                <InputError
                                    id="tenant-logo-error"
                                    message={errors['logo']}
                                />
                            </div>
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
