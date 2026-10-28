import TenantRowView from '@/components/tenancy/tenant-row';
import {
    Table,
    TableBody,
    TableCaption,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { TenantRow } from '@/types';

export default function TenantsTable({
    tenants,
    activationMinDate,
}: {
    tenants: TenantRow[];
    activationMinDate: string;
}) {
    return (
        <Table>
            <TableCaption className="sr-only">
                Empresas, de la más reciente a la más antigua
            </TableCaption>
            <TableHeader>
                <TableRow>
                    <TableHead>Empresa</TableHead>
                    <TableHead>RFC</TableHead>
                    <TableHead>Estado</TableHead>
                    <TableHead>
                        <span className="sr-only">Acciones</span>
                    </TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {tenants.map((tenant) => (
                    <TenantRowView
                        key={tenant.id}
                        tenant={tenant}
                        activationMinDate={activationMinDate}
                    />
                ))}
            </TableBody>
        </Table>
    );
}
