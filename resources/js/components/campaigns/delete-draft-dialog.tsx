import { Trash2 } from 'lucide-react';
import ConfirmDialog from '@/components/humetria/confirm-dialog';
import { Button } from '@/components/ui/button';
import { destroy } from '@/routes/tenant/campaigns';

/** Throws a draft away, after saying nobody was invited yet. */
export default function DeleteDraftDialog({
    tenantSlug,
    campaignId,
}: {
    tenantSlug: string;
    campaignId: string;
}) {
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
            description="Nadie fue invitado todavía. Podrás preparar otra evaluación de este centro cuando quieras."
            confirmLabel="Eliminar borrador"
            form={destroy.form([tenantSlug, campaignId])}
        />
    );
}
