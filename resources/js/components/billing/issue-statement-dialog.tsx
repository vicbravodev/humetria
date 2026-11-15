import { Send } from 'lucide-react';
import ConfirmDialog from '@/components/humetria/confirm-dialog';
import { Button } from '@/components/ui/button';
import { issue } from '@/routes/billing/statements';
import type { StatementDetail } from '@/types';

/** Issues a draft: from then on it is owed and cannot be edited, only corrected with an adjustment. */
export default function IssueStatementDialog({
    statement,
}: {
    statement: StatementDetail;
}) {
    return (
        <ConfirmDialog
            trigger={
                <Button>
                    <Send aria-hidden />
                    Emitir
                </Button>
            }
            title={`Emitir el estado de cuenta de ${statement.period}`}
            description={`Se deberán ${statement.total} con IVA. Una vez emitido no se puede editar ni borrar; si algo está mal, se corrige con un ajuste. Emitirlo no cambia el acceso de la empresa.`}
            confirmLabel="Confirmar emisión"
            destructive={false}
            form={issue.form(statement.id)}
            preserveScroll
        />
    );
}
