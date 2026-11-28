import SubscriptionForm from '@/components/billing/subscription-form';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import type { AccountCompanyRow, AccountKind } from '@/types';

/** Gives a company its plan, or changes the one it has: with the price beside the form, before saving. Whoever opens it holds it open. */
export default function SubscriptionDialog({
    company,
    accountKind,
    today,
    open,
    onOpenChange,
}: {
    company: AccountCompanyRow;
    accountKind: AccountKind;
    today: string;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const has = company.subscription !== null;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90svh] overflow-y-auto sm:max-w-4xl">
                <DialogTitle>
                    {has ? 'Editar plan' : 'Asignar plan'} ·{' '}
                    {company.trade_name}
                </DialogTitle>
                <DialogDescription>
                    Las herramientas pasan a ser las del plan.
                </DialogDescription>
                <SubscriptionForm
                    company={company}
                    accountKind={accountKind}
                    today={today}
                    onDone={() => onOpenChange(false)}
                />
            </DialogContent>
        </Dialog>
    );
}
