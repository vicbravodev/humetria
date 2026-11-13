import { Link } from '@inertiajs/react';
import { Eye } from 'lucide-react';
import { useState } from 'react';
import FormDialog from '@/components/humetria/form-dialog';
import InputError from '@/components/humetria/input-error';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { page } from '@/routes/tenant/disclosure';
import { publish } from '@/routes/tenant/disclosure/results';
import type { ResultsRow } from '@/types';

/**
 * Publishes the results of an evaluation to the people (num. 7.8; criterio C-54), after saying who will see them. RH may add the
 * measures of the Programa that are for the whole center: nothing of the Programa reaches the people without that choice.
 */
export default function PublishResultsButton({
    row,
    pageActive,
}: {
    row: ResultsRow;
    pageActive: boolean;
}) {
    const tenantSlug = useTenantSlug();
    const [open, setOpen] = useState(false);

    return (
        <>
            <Button size="sm" onClick={() => setOpen(true)}>
                <Eye aria-hidden />
                Publicar
                <span className="sr-only">
                    {' '}
                    los resultados de {row.workCenter}, {row.closedOn}
                </span>
            </Button>
            {open && (
                <FormDialog
                    open={open}
                    onOpenChange={setOpen}
                    title="¿Publicar los resultados para las personas?"
                    description="Estas cifras las verá cualquier persona con el enlace de la página: cuántos cuestionarios quedan en cada nivel de riesgo, de todo el centro, sin áreas. Si el riesgo es alto, conviene tener listas las medidas antes de publicar."
                    form={publish.form({
                        tenant: tenantSlug,
                        campaign: row.id,
                    })}
                    submit="Publicar"
                >
                    {(errors) => (
                        <>
                            {!pageActive && (
                                <Alert>
                                    <AlertDescription>
                                        Aún no tienen dónde verlos: la página de
                                        las personas no está activa.{' '}
                                        <Link
                                            href={page.url(tenantSlug)}
                                            className="font-medium underline underline-offset-4"
                                        >
                                            Crear el enlace
                                        </Link>
                                    </AlertDescription>
                                </Alert>
                            )}
                            <label className="flex cursor-pointer items-start gap-3">
                                <input
                                    type="checkbox"
                                    name="show_measures"
                                    value="1"
                                    className="mt-0.5 size-5 shrink-0 accent-primary"
                                />
                                <span>
                                    Mostrar también las medidas del Programa de
                                    intervención que son para todo el centro.
                                    <span className="block text-sm text-muted-foreground">
                                        Se muestran el título, el estado y la
                                        fecha de cada una; no las de áreas ni
                                        las de atención individual. No escribas
                                        nombres en los títulos.
                                    </span>
                                </span>
                            </label>
                            <InputError message={errors['campaign']} />
                        </>
                    )}
                </FormDialog>
            )}
        </>
    );
}
