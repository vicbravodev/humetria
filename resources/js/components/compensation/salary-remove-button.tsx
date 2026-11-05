import { Trash2 } from 'lucide-react';
import ConfirmDialog from '@/components/humetria/confirm-dialog';
import { Button } from '@/components/ui/button';
import { describeDate } from '@/lib/calendar-date';
import type { SalaryEntry } from '@/types';
import type { RouteFormDefinition } from '@/wayfinder';

/** Takes out a salary typed by mistake, once confirmed: the one before it is in force again. */
export default function SalaryRemoveButton({
    salary,
    form,
}: {
    salary: SalaryEntry;
    form: RouteFormDefinition<'post'>;
}) {
    const since = describeDate(salary.effective_from) ?? salary.effective_from;

    return (
        // The dialog's own form must not reach the form of the sheet this sits in: a React event crosses a portal.
        <span onSubmit={(event) => event.stopPropagation()}>
            <ConfirmDialog
                trigger={
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label={`Eliminar el salario desde el ${since}`}
                        className="text-muted-foreground hover:text-destructive"
                    >
                        <Trash2 aria-hidden />
                    </Button>
                }
                title="¿Eliminar este salario?"
                description={`Es el de ${salary.daily_salary_text} desde el ${since}. Úsalo para un error de captura: el salario anterior vuelve a regir y los escenarios se calculan de nuevo con él. Para un cambio de verdad, captura la vigencia nueva en lugar de borrar.`}
                confirmLabel="Eliminar salario"
                form={form}
                preserveScroll
            />
        </span>
    );
}
