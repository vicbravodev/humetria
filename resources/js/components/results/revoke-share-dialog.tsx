import ConfirmDialog from '@/components/humetria/confirm-dialog';
import { Button } from '@/components/ui/button';
import { destroy } from '@/routes/tenant/campaigns/shares';
import type { ReportShareRow } from '@/types';

/** Revokes one link to the report, once confirmed: whoever has it stops opening the report at once. */
export default function RevokeShareDialog({
    route,
    share,
}: {
    /** The company's slug and the campaign's id. */
    route: [string, string];
    share: ReportShareRow;
}) {
    return (
        <ConfirmDialog
            trigger={
                <Button
                    variant="outline"
                    size="sm"
                    aria-label={`Revocar el enlace que vence el ${share.expiresAt}`}
                >
                    Revocar
                </Button>
            }
            title="¿Revocar este enlace?"
            description="Quien lo tenga deja de abrir el informe en ese momento. Si lo necesita otra vez, crea un enlace nuevo."
            confirmLabel="Revocar enlace"
            form={destroy.form([...route, share.id])}
            preserveScroll
        />
    );
}
