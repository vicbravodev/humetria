import AccountCompanyRowView from '@/components/billing/account-company-row';
import {
    Table,
    TableBody,
    TableCaption,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { AccountCompanyRow, AccountKind } from '@/types';

export default function AccountCompaniesTable({
    companies,
    accountKind,
    today,
}: {
    companies: AccountCompanyRow[];
    accountKind: AccountKind;
    today: string;
}) {
    return (
        <Table>
            <TableCaption className="sr-only">
                Empresas que paga la cuenta, de la vinculada más reciente a la
                más antigua
            </TableCaption>
            <TableHeader>
                <TableRow>
                    <TableHead>Empresa</TableHead>
                    <TableHead>Estado</TableHead>
                    <TableHead>Plan</TableHead>
                    <TableHead>Tamaño</TableHead>
                    <TableHead>Al mes, sin IVA</TableHead>
                    <TableHead>Renueva</TableHead>
                    <TableHead>
                        <span className="sr-only">Acciones</span>
                    </TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {companies.map((company) => (
                    <AccountCompanyRowView
                        key={company.company_id}
                        company={company}
                        accountKind={accountKind}
                        today={today}
                    />
                ))}
            </TableBody>
        </Table>
    );
}
