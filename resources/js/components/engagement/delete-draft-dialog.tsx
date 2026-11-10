import { Trash2 } from 'lucide-react';
import ConfirmDialog from '@/components/humetria/confirm-dialog';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { destroy } from '@/routes/tenant/climate';

/** Throws a draft away, after saying nobody was invited yet. */
export default function DeleteDraftDialog({ surveyId }: { surveyId: string }) {
    const tenantSlug = useTenantSlug();

    return (
        <ConfirmDialog
            trigger={
                <Button
                    variant="ghost"
                    className="text-destructive hover:text-destructive"
                >
                    <Trash2 aria-hidden="true" />
                    Eliminar borrador
                </Button>
            }
            title="¿Eliminar este borrador?"
            description="Nadie fue invitado todavía. Podrás preparar otra encuesta cuando quieras."
            confirmLabel="Eliminar borrador"
            form={destroy.form([tenantSlug, surveyId])}
        />
    );
}
