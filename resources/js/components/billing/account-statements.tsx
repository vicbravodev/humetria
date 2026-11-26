import { usePage } from '@inertiajs/react';
import AccountStatementRowView from '@/components/billing/account-statement-row';
import CompanyPickerAction from '@/components/billing/company-picker-action';
import StatementViewChips from '@/components/billing/statement-view-chips';
import Pagination from '@/components/humetria/pagination';
import {
    Table,
    TableBody,
    TableCaption,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type {
    AccountCompanyRow,
    AccountKind,
    Paginated,
    StatementRow,
} from '@/types';

/** «Estados de cuenta» (tablero Cuenta de facturación): all of them or only the open ones, newest month first, with the way to make a new draft. */
export default function AccountStatements({
    accountId,
    accountKind,
    today,
    statements,
    view,
    companies,
}: {
    accountId: string;
    accountKind: AccountKind;
    today: string;
    statements: Paginated<StatementRow>;
    view: 'all' | 'open';
    companies: AccountCompanyRow[];
}) {
    const { auth } = usePage().props;

    return (
        <section
            aria-labelledby="account-statements"
            className="overflow-hidden card p-0 [&_[data-slot=table-container]]:rounded-none [&_[data-slot=table-container]]:border-0"
        >
            <div className="flex flex-wrap items-center gap-3 border-b px-6 py-4">
                <h2 id="account-statements" className="flex-1 font-semibold">
                    Estados de cuenta
                </h2>
                <StatementViewChips accountId={accountId} view={view} />
                {auth.isSuperadmin && (
                    <CompanyPickerAction
                        kind="statement"
                        companies={companies.filter(
                            ({ subscription }) => subscription !== null,
                        )}
                        accountKind={accountKind}
                        today={today}
                    >
                        Generar estado
                    </CompanyPickerAction>
                )}
            </div>
            {statements.data.length === 0 ? (
                <p className="px-6 py-4 text-muted-foreground">
                    {view === 'open'
                        ? 'No hay estados de cuenta abiertos.'
                        : 'Aún no hay estados de cuenta.'}
                </p>
            ) : (
                <Table className="min-w-[640px]">
                    <TableCaption className="sr-only">
                        Estados de cuenta de la cuenta, del mes más reciente al
                        más antiguo
                    </TableCaption>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Periodo</TableHead>
                            <TableHead>Estado</TableHead>
                            <TableHead>Emisión</TableHead>
                            <TableHead>Vence</TableHead>
                            <TableHead className="text-right">Total</TableHead>
                            <TableHead className="text-right">Saldo</TableHead>
                            <TableHead>
                                <span className="sr-only">Abrir</span>
                            </TableHead>
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
            )}
            <div className="border-t px-6 py-3 empty:hidden">
                <Pagination
                    previous="Anterior"
                    next="Siguiente"
                    page={statements}
                    label="Páginas de estados de cuenta"
                    pinned={false}
                />
            </div>
        </section>
    );
}
