import { usePage } from '@inertiajs/react';
import AccountFigure from '@/components/billing/account-figure';
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
import { countOf } from '@/lib/plural';
import type { Paginated, Receivable, StatementRow } from '@/types';

/** The statements of the account, newest month first, with what it still owes in all and the way to record a payment. */
export default function AccountStatements({
    accountId,
    today,
    statements,
    receivable,
}: {
    accountId: string;
    today: string;
    statements: Paginated<StatementRow>;
    receivable: Receivable;
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
            <dl className="grid gap-6 card p-6 sm:grid-cols-2">
                <AccountFigure
                    label="Saldo por cobrar"
                    value={receivable.outstanding}
                    hint={
                        receivable.open_statements === 0
                            ? 'Sin estados de cuenta pendientes'
                            : `${countOf(receivable.open_statements, 'estado de cuenta', 'estados de cuenta')} con saldo`
                    }
                />
            </dl>
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
