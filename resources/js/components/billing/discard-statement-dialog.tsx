import { Trash2 } from 'lucide-react';
import ConfirmDialog from '@/components/humetria/confirm-dialog';
import { Button } from '@/components/ui/button';
import { destroy } from '@/routes/billing/statements';
import type { StatementDetail } from '@/types';

/** Throws away a draft: it was never owed. What it billed once goes back to be billed by the next draft of that month. */
export default function DiscardStatementDialog({
    statement,
}: {
    statement: StatementDetail;
}) {
    return (
        <ConfirmDialog
            trigger={
                <Button variant="outline">
                    <Trash2 aria-hidden />
                    Descartar borrador
                </Button>
            }
            title={`Descartar el borrador de ${statement.period}`}
            description="El borrador se borra con sus líneas. Los cargos de una sola vez que facturaba quedan libres para el siguiente borrador del mes. Un estado de cuenta emitido nunca se descarta."
            confirmLabel="Confirmar descarte"
            form={destroy.form(statement.id)}
        />
    );
}
