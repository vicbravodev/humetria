import AccountCompanyRowView from '@/components/billing/account-company-row';
import {
    Table,
    TableBody,
    TableCaption,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { AccountCompanyRow } from '@/types';

export default function AccountCompaniesTable({
    companies,
}: {
    companies: AccountCompanyRow[];
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
                    <TableHead>Centros</TableHead>
                    <TableHead>Plan</TableHead>
                    <TableHead>Vínculo</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {companies.map((company) => (
                    <AccountCompanyRowView
                        key={company.company_id}
                        company={company}
                    />
                ))}
            </TableBody>
        </Table>
    );
}
