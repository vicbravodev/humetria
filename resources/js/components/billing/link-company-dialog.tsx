import { Link2 } from 'lucide-react';
import { useState } from 'react';
import Combobox from '@/components/humetria/combobox';
import DatePicker from '@/components/humetria/date-picker';
import FormDialog from '@/components/humetria/form-dialog';
import InputError from '@/components/humetria/input-error';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { serverSource } from '@/lib/server-options';
import { store } from '@/routes/billing/accounts/companies';
import { search } from '@/routes/billing/companies';

/** Makes an existing company one the account pays: searched by name or RFC among the ones with no payer, from a day. */
export default function LinkCompanyDialog({
    accountId,
}: {
    accountId: string;
}) {
    const [open, setOpen] = useState(false);

    return (
        <>
            <Button onClick={() => setOpen(true)}>
                <Link2 aria-hidden />
                Vincular empresa
            </Button>
            <FormDialog
                open={open}
                onOpenChange={setOpen}
                title="Vincular empresa"
                description="Elige una empresa que aún no tenga quien la pague. Seguirá siendo una empresa aparte: pagar no da acceso a sus datos."
                form={store.form(accountId)}
                submit="Vincular"
            >
                {(errors) => (
                    <div className="grid gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="billing-company_id">Empresa</Label>
                            <Combobox
                                id="billing-company_id"
                                name="company_id"
                                source={serverSource(search.url())}
                                placeholder="Busca por nombre o RFC"
                                invalid={errors['company_id'] !== undefined}
                                describedBy="billing-company_id-error"
                            />
                            <InputError
                                id="billing-company_id-error"
                                message={errors['company_id']}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="billing-starts_on">
                                Paga desde
                            </Label>
                            <DatePicker
                                id="billing-starts_on"
                                name="starts_on"
                                invalid={errors['starts_on'] !== undefined}
                                describedBy="billing-starts_on-error"
                            />
                            <InputError
                                id="billing-starts_on-error"
                                message={errors['starts_on']}
                            />
                            <p className="text-sm text-muted-foreground">
                                Si no eliges un día, desde hoy.
                            </p>
                        </div>
                    </div>
                )}
            </FormDialog>
        </>
    );
}
