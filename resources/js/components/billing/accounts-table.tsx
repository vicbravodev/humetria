import AccountRowView from '@/components/billing/account-row';
import {
    Table,
    TableBody,
    TableCaption,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { AccountRow } from '@/types';

export default function AccountsTable({
    accounts,
}: {
    accounts: AccountRow[];
}) {
    return (
        <Table>
            <TableCaption className="sr-only">
                Cuentas, de la más reciente a la más antigua
            </TableCaption>
            <TableHeader>
                <TableRow>
                    <TableHead>Cuenta</TableHead>
                    <TableHead>Tipo</TableHead>
                    <TableHead>Empresas</TableHead>
                    <TableHead>Contacto</TableHead>
                    <TableHead>Saldo vencido</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {accounts.map((account) => (
                    <AccountRowView key={account.id} account={account} />
                ))}
            </TableBody>
        </Table>
    );
}
