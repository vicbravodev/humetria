import { usePage } from '@inertiajs/react';
import AccountStatementRowView from '@/components/billing/account-statement-row';
import RecordPaymentDialog from '@/components/billing/record-payment-dialog';
import Pagination from '@/components/humetria/pagination';
import {
    Table,
    TableBody,
    TableCaption,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { Paginated, StatementRow } from '@/types';

/** The statements of the account, newest month first, with the way to record a payment (what it owes is in the side panel). */
export default function AccountStatements({
    accountId,
    today,
    statements,
}: {
    accountId: string;
    today: string;
    statements: Paginated<StatementRow>;
}) {
    const { auth } = usePage().props;

    return (
        <section aria-labelledby="account-statements" className="grid gap-4">
            <div className="flex flex-wrap items-end justify-between gap-4">
                <h2
                    id="account-statements"
                    className="text-xl font-semibold tracking-[-0.01em]"
                >
                    Estados de cuenta
                </h2>
                {auth.isSuperadmin && (
                    <RecordPaymentDialog accountId={accountId} today={today} />
                )}
            </div>
            <Table>
                <TableCaption className="sr-only">
                    Estados de cuenta de la cuenta, del mes más reciente al más
                    antiguo
                </TableCaption>
                <TableHeader>
                    <TableRow>
                        <TableHead>Periodo</TableHead>
                        <TableHead>Empresa</TableHead>
                        <TableHead>Total</TableHead>
                        <TableHead>Pagado</TableHead>
                        <TableHead>Saldo</TableHead>
                        <TableHead>Estado</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {statements.data.map((statement) => (
                        <AccountStatementRowView
                            key={statement.id}
                            statement={statement}
                        />
                    ))}
                </TableBody>
            </Table>
            <Pagination
                previous="Ver más recientes"
                next="Ver más antiguos"
                page={statements}
                label="Páginas de estados de cuenta"
            />
        </section>
    );
}
