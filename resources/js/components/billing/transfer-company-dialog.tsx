import TransferCompanyForm from '@/components/billing/transfer-company-form';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import type { AccountCompanyRow } from '@/types';

/** Moves a company to another account, or to none, from a cut and with a reason. It never gives or takes access from anyone. */
export default function TransferCompanyDialog({
    company,
    today,
    open,
    onOpenChange,
}: {
    company: AccountCompanyRow;
    today: string;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90svh] overflow-y-auto sm:max-w-4xl">
                <DialogTitle>Trasladar · {company.trade_name}</DialogTitle>
                <DialogDescription>
                    Los estados de cuenta ya hechos se quedan con la cuenta de
                    antes; los siguientes van a la nueva. La empresa conserva su
                    plan y su gente conserva su acceso.
                </DialogDescription>
                <TransferCompanyForm
                    company={company}
                    today={today}
                    onDone={() => onOpenChange(false)}
                />
            </DialogContent>
        </Dialog>
    );
}
