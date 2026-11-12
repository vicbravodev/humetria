import { usePage } from '@inertiajs/react';
import AccountChargeRowView from '@/components/billing/account-charge-row';
import Pagination from '@/components/humetria/pagination';
import {
    Table,
    TableBody,
    TableCaption,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { AccountChargeRow, Paginated } from '@/types';

/** The extra charges of the companies the account pays, newest first, a page at a time. Statements will add them to the plans. */
export default function AccountCharges({
    charges,
}: {
    charges: Paginated<AccountChargeRow>;
}) {
    const { auth } = usePage().props;

    return (
        <section aria-labelledby="account-charges" className="grid gap-4">
            <h2
                id="account-charges"
                className="text-xl font-semibold tracking-[-0.01em]"
            >
                Cargos extra
            </h2>
            <Table>
                <TableCaption className="sr-only">
                    Cargos extra de las empresas de la cuenta, del más reciente
                    al más antiguo
                </TableCaption>
                <TableHeader>
                    <TableRow>
                        <TableHead>Cargo</TableHead>
                        <TableHead>Empresa</TableHead>
                        <TableHead>Tipo</TableHead>
                        <TableHead>Importe</TableHead>
                        <TableHead>Fechas</TableHead>
                        <TableHead>Aprobó</TableHead>
                        {auth.isSuperadmin && (
                            <TableHead>
                                <span className="sr-only">Acciones</span>
                            </TableHead>
                        )}
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {charges.data.map((charge) => (
                        <AccountChargeRowView key={charge.id} charge={charge} />
                    ))}
                </TableBody>
            </Table>
            <Pagination
                previous="Ver más recientes"
                next="Ver más antiguos"
                page={charges}
                label="Páginas de cargos extra"
            />
        </section>
    );
}
