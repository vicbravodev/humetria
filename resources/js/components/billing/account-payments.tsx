import { usePage } from '@inertiajs/react';
import AccountPaymentRowView from '@/components/billing/account-payment-row';
import RecordPaymentDialog from '@/components/billing/record-payment-dialog';
import Pagination from '@/components/humetria/pagination';
import type { Paginated, PaymentRow } from '@/types';

/** «Pagos y aplicaciones» (tablero Cuenta de facturación): each transfer, the latest first, with the months it settled; a wrong one is reversed with a reason, never deleted. */
export default function AccountPayments({
    accountId,
    today,
    payments,
}: {
    accountId: string;
    today: string;
    payments: Paginated<PaymentRow>;
}) {
    const { auth } = usePage().props;

    return (
        <section aria-labelledby="account-payments" className="card p-0">
            <div className="flex items-center gap-3 border-b px-6 py-4">
                <h2 id="account-payments" className="flex-1 font-semibold">
                    Pagos y aplicaciones
                </h2>
                {auth.isSuperadmin && (
                    <RecordPaymentDialog
                        accountId={accountId}
                        today={today}
                        variant="outline"
                    />
                )}
            </div>
            {payments.data.length === 0 ? (
                <p className="px-6 py-4 text-muted-foreground">
                    Aún no hay pagos registrados.
                </p>
            ) : (
                <ul className="divide-y">
                    {payments.data.map((payment) => (
                        <AccountPaymentRowView
                            key={payment.id}
                            accountId={accountId}
                            payment={payment}
                            canManage={auth.isSuperadmin}
                        />
                    ))}
                </ul>
            )}
            <div className="border-t px-6 py-3 empty:hidden">
                <Pagination
                    previous="Anterior"
                    next="Siguiente"
                    page={payments}
                    label="Páginas de pagos"
                    pinned={false}
                />
            </div>
        </section>
    );
}
