import { useState } from 'react';
import SubscriptionForm from '@/components/billing/subscription-form';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import type { AccountCompanyRow, AccountKind } from '@/types';

/** Gives a company its plan, or changes the one it has, from its row: with the price beside the form, before saving. */
export default function SubscriptionDialog({
    company,
    accountKind,
    today,
}: {
    company: AccountCompanyRow;
    accountKind: AccountKind;
    today: string;
}) {
    const [open, setOpen] = useState(false);
    const has = company.subscription !== null;

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    variant={has ? 'ghost' : 'outline'}
                    size="sm"
                    aria-label={`${has ? 'Editar plan' : 'Asignar plan'} de ${company.trade_name}`}
                >
                    {has ? 'Editar plan' : 'Asignar plan'}
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90svh] overflow-y-auto sm:max-w-4xl">
                <DialogTitle>
                    {has ? 'Editar plan' : 'Asignar plan'} ·{' '}
                    {company.trade_name}
                </DialogTitle>
                <DialogDescription>
                    Cambiar el plan no cambia el acceso de la empresa: sus
                    fechas, sus módulos y su estado siguen como están.
                </DialogDescription>
                <SubscriptionForm
                    company={company}
                    accountKind={accountKind}
                    today={today}
                    onDone={() => setOpen(false)}
                />
            </DialogContent>
        </Dialog>
    );
}
