import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { saveBarText } from '@/lib/leave/policy';
import { index } from '@/routes/tenant/vacations/policies';

/**
 * The bar that stays at the bottom of the editor: what the form holds (nothing to save, changes, or how many fields to fix),
 * «Cancelar» and the primary. A viewer only gets the way back.
 */
export default function PolicySaveBar({
    dirty,
    errors,
    processing,
    isNew,
    canManage,
}: {
    dirty: boolean;
    errors: number;
    processing: boolean;
    isNew: boolean;
    canManage: boolean;
}) {
    const tenantSlug = useTenantSlug();

    return (
        <div
            role="region"
            aria-label="Guardado de la política"
            className="sticky bottom-0 z-10 -mx-4 flex flex-wrap items-center gap-3 border-t bg-background px-4 py-3 md:-mx-10 md:px-10"
        >
            {canManage && (
                <p
                    aria-live="polite"
                    className={
                        errors > 0
                            ? 'mr-auto text-sm font-medium text-destructive'
                            : 'mr-auto text-sm text-muted-foreground'
                    }
                >
                    {saveBarText({ dirty, errors })}
                </p>
            )}
            <Button variant="outline" asChild className="max-sm:h-11">
                <Link href={index(tenantSlug)}>
                    {canManage ? 'Cancelar' : 'Volver'}
                </Link>
            </Button>
            {canManage && (
                <Button
                    type="submit"
                    disabled={processing}
                    className="max-sm:h-11"
                >
                    {processing && <Spinner />}
                    {isNew ? 'Crear política' : 'Guardar política'}
                </Button>
            )}
        </div>
    );
}
