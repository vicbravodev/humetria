import { useState } from 'react';
import GenerateStatementForm from '@/components/billing/generate-statement-form';
import FormDialog from '@/components/humetria/form-dialog';
import { Button } from '@/components/ui/button';
import { defaultPeriod } from '@/lib/billing-statements';
import { store } from '@/routes/billing/statements';
import type { AccountCompanyRow } from '@/types';

/** Makes the draft of a month's statement for a company with a plan, after showing its lines. Nothing is owed until it is issued. */
export default function GenerateStatementDialog({
    company,
    today,
}: {
    company: AccountCompanyRow;
    today: string;
}) {
    const [open, setOpen] = useState(false);

    return (
        <>
            <Button
                variant="ghost"
                size="sm"
                onClick={() => setOpen(true)}
                aria-label={`Generar estado de cuenta de ${company.trade_name}`}
            >
                Generar estado de cuenta
            </Button>
            <FormDialog
                open={open}
                onOpenChange={setOpen}
                title={`Generar estado de cuenta · ${company.trade_name}`}
                description="Se hace un borrador con los importes del plan y los cargos del mes; no se debe nada hasta que lo emitas, y ya emitido no se puede editar."
                form={store.form(company.company_id)}
                submit="Generar borrador"
            >
                {(errors) => (
                    <GenerateStatementForm
                        companyId={company.company_id}
                        defaultPeriod={defaultPeriod(today)}
                        error={errors['period']}
                    />
                )}
            </FormDialog>
        </>
    );
}
