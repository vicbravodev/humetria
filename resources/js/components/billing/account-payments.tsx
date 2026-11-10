import { usePage } from '@inertiajs/react';
import AccountPaymentRowView from '@/components/billing/account-payment-row';
import Pagination from '@/components/humetria/pagination';
import {
    Table,
    TableBody,
    TableCaption,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { Paginated, PaymentRow } from '@/types';

/** The transfers recorded for the account, the latest first: a wrong one is reversed with a reason, never deleted. */
export default function AccountPayments({
    payments,
}: {
    payments: Paginated<PaymentRow>;
}) {
    const { auth } = usePage().props;

    return (
        <section aria-labelledby="account-payments" className="grid gap-4">
            <h2
                id="account-payments"
                className="text-xl font-semibold tracking-[-0.01em]"
            >
                Pagos
            </h2>
            <Table>
                <TableCaption className="sr-only">
                    Pagos de la cuenta, del más reciente al más antiguo
                </TableCaption>
                <TableHeader>
                    <TableRow>
                        <TableHead>Referencia</TableHead>
                        <TableHead>Fecha</TableHead>
                        <TableHead>Importe</TableHead>
                        <TableHead>Aplicado</TableHead>
                        <TableHead>Sin aplicar</TableHead>
                        <TableHead>Registró</TableHead>
                        <TableHead>
                            <span className="sr-only">Acciones</span>
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {payments.data.map((payment) => (
                        <AccountPaymentRowView
                            key={payment.id}
                            payment={payment}
                            canManage={auth.isSuperadmin}
                        />
                    ))}
                </TableBody>
            </Table>
            <Pagination
                previous="Ver más recientes"
                next="Ver más antiguos"
                page={payments}
                label="Páginas de pagos"
            />
        </section>
    );
}
