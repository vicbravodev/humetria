import AccountRowView from '@/components/billing/account-row';
import ChooseBox from '@/components/humetria/choose-box';
import {
    Table,
    TableBody,
    TableCaption,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { IdSelection } from '@/hooks/use-id-selection';
import type { AccountRow } from '@/types';

/** The accounts of a view, newest first; the ticks are only for whoever may remind and record payments. */
export default function AccountsTable({
    accounts,
    selection,
    canManage,
}: {
    accounts: AccountRow[];
    selection: IdSelection;
    canManage: boolean;
}) {
    return (
        <Table>
            <TableCaption className="sr-only">
                Cuentas, de la más reciente a la más antigua
            </TableCaption>
            <TableHeader>
                <TableRow>
                    {canManage && (
                        <TableHead className="w-10">
                            <ChooseBox
                                checked={selection.allChosen}
                                onChange={selection.toggleAll}
                                name="Elegir todas las cuentas de esta página"
                            />
                        </TableHead>
                    )}
                    <TableHead>Cuenta</TableHead>
                    <TableHead>Tipo</TableHead>
                    <TableHead>Empresas</TableHead>
                    <TableHead className="text-right">Por cobrar</TableHead>
                    <TableHead>Antigüedad</TableHead>
                    <TableHead>Último pago</TableHead>
                    <TableHead>Acción sugerida</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {accounts.map((account) => (
                    <AccountRowView
                        key={account.id}
                        account={account}
                        canManage={canManage}
                        chosen={selection.isChosen(account.id)}
                        onChoose={(checked) =>
                            selection.toggle(account.id, checked)
                        }
                    />
                ))}
            </TableBody>
        </Table>
    );
}
