import type { ReactNode } from 'react';
import { useState } from 'react';
import CompanyDialogs from '@/components/billing/company-dialogs';
import CompanyPickerDialog from '@/components/billing/company-picker-dialog';
import { Button } from '@/components/ui/button';
import type {
    AccountCompanyRow,
    AccountKind,
    CompanyDialogKind,
} from '@/types';

/**
 * An action on one of the account's companies, from outside its line («Agregar cargo», «Generar estado», «Transferir…»):
 * with one company it opens right away; with more it first asks which.
 */
export default function CompanyPickerAction({
    kind,
    companies,
    accountKind,
    today,
    children,
    variant = 'outline',
    size = 'sm',
}: {
    kind: CompanyDialogKind;
    /** The companies it applies to (of the page on screen). */
    companies: AccountCompanyRow[];
    accountKind: AccountKind;
    today: string;
    /** The button's content. */
    children: ReactNode;
    variant?: 'outline' | 'ghost';
    size?: 'sm' | 'default';
}) {
    const [picking, setPicking] = useState(false);
    const [target, setTarget] = useState<AccountCompanyRow | null>(null);
    const [only] = companies;

    if (only === undefined) {
        return null;
    }

    return (
        <>
            <Button
                variant={variant}
                size={size}
                onClick={() =>
                    companies.length === 1 ? setTarget(only) : setPicking(true)
                }
            >
                {children}
            </Button>
            <CompanyPickerDialog
                open={picking}
                onOpenChange={setPicking}
                companies={companies}
                onPick={(company) => {
                    setPicking(false);
                    setTarget(company);
                }}
            />
            {target !== null && (
                <CompanyDialogs
                    company={target}
                    accountKind={accountKind}
                    today={today}
                    open={kind}
                    onClose={() => setTarget(null)}
                />
            )}
        </>
    );
}
