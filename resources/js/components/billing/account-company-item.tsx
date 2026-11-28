import { usePage } from '@inertiajs/react';
import { useState } from 'react';
import CompanyDialogs from '@/components/billing/company-dialogs';
import Initials from '@/components/humetria/initials';
import RowMenu from '@/components/humetria/row-menu';
import type { RowMenuItem } from '@/components/humetria/row-menu';
import { Button } from '@/components/ui/button';
import { companyLine } from '@/lib/billing-account';
import type {
    AccountCompanyRow,
    AccountKind,
    CompanyDialogKind,
} from '@/types';

/**
 * A company the account pays, in a line (tablero Cuenta de facturación): its plan and seats and what it pays a month before
 * VAT, or the way to give it a plan; the rest of what can be done with it in its menu, for the superadmin.
 */
export default function AccountCompanyItem({
    company,
    accountKind,
    today,
}: {
    company: AccountCompanyRow;
    accountKind: AccountKind;
    today: string;
}) {
    const { auth } = usePage().props;
    const [dialog, setDialog] = useState<CompanyDialogKind | null>(null);
    const { subscription } = company;
    const items: RowMenuItem[] = [
        ...(subscription === null
            ? []
            : [
                  { label: 'Editar plan', onSelect: () => setDialog('plan') },
                  {
                      label: 'Generar estado de cuenta',
                      onSelect: () => setDialog('statement'),
                  },
                  {
                      label: 'Agregar cargo',
                      onSelect: () => setDialog('charge'),
                  },
              ]),
        { label: 'Trasladar', onSelect: () => setDialog('transfer') },
    ];

    return (
        <li className="flex items-center gap-3 px-6 py-3.5">
            <Initials name={company.trade_name} kind="company" />
            <div className="min-w-0 flex-1">
                <p className="truncate font-medium">{company.trade_name}</p>
                <p className="text-sm text-muted-foreground">
                    {companyLine(company)}
                </p>
                {company.previous_payer !== null && (
                    <p className="text-caption text-muted-foreground">
                        Antes la pagaba {company.previous_payer}
                    </p>
                )}
                {subscription?.scheduled != null && (
                    <p
                        data-test="plan-scheduled"
                        className="text-caption text-muted-foreground"
                    >
                        El {subscription.scheduled.effective_on}:{' '}
                        {subscription.scheduled.summary}
                    </p>
                )}
            </div>
            {subscription === null ? (
                auth.isSuperadmin && (
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => setDialog('plan')}
                        aria-label={`Asignar plan de ${company.trade_name}`}
                    >
                        Asignar plan
                    </Button>
                )
            ) : (
                <span className="font-medium tabular-nums">
                    {subscription.quote.requires_quotation
                        ? 'Requiere cotización'
                        : subscription.quote.subtotal}
                </span>
            )}
            {auth.isSuperadmin && (
                <>
                    <RowMenu
                        label={`Acciones de ${company.trade_name}`}
                        items={items}
                    />
                    <CompanyDialogs
                        company={company}
                        accountKind={accountKind}
                        today={today}
                        open={dialog}
                        onClose={() => setDialog(null)}
                    />
                </>
            )}
        </li>
    );
}
