import { Form, Link } from '@inertiajs/react';
import InputError from '@/components/humetria/input-error';
import { Button } from '@/components/ui/button';
import { DialogFooter } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { update as updateColor } from '@/routes/companies/brand-color';
import type { TenantRow } from '@/types';

/** The accent of a company's pages: a color to pick, or back to the one of Humetría. */
export default function TenantBrandColorForm({
    tenant,
    onDone,
}: {
    tenant: TenantRow;
    onDone: () => void;
}) {
    return (
        <Form
            {...updateColor.form(tenant.id)}
            onSuccess={onDone}
            className="grid gap-3 border-t pt-6"
        >
            {({ processing, errors }) => (
                <>
                    <div className="grid gap-2">
                        <Label htmlFor="tenant-brand-color">
                            Color de acento
                        </Label>
                        <div className="flex items-center gap-3">
                            <input
                                id="tenant-brand-color"
                                type="color"
                                name="brand_color"
                                defaultValue={tenant.brand_color ?? '#0b7a6f'}
                                className="h-11 w-14 cursor-pointer rounded-md border bg-card p-1"
                            />
                            <p className="text-sm text-muted-foreground">
                                Se ajusta solo, en claro y en oscuro, para que
                                el texto se lea bien.
                            </p>
                        </div>
                        <InputError message={errors['brand_color']} />
                    </div>
                    <DialogFooter className="gap-2">
                        {tenant.brand_color !== null && (
                            <Button variant="ghost" asChild>
                                <Link
                                    href={updateColor(tenant.id)}
                                    method="put"
                                    data={{ brand_color: null }}
                                    as="button"
                                    onSuccess={onDone}
                                >
                                    Volver al de Humetría
                                </Link>
                            </Button>
                        )}
                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner />}
                            Guardar color
                        </Button>
                    </DialogFooter>
                </>
            )}
        </Form>
    );
}
