import TenantRowView from '@/components/tenancy/tenant-row';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Table,
    TableBody,
    TableCaption,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { IdSelection } from '@/hooks/use-id-selection';
import type { TenantRow } from '@/types';

export default function TenantsTable({
    tenants,
    activationMinDate,
    selection,
}: {
    tenants: TenantRow[];
    activationMinDate: string;
    /** Only the superadmin chooses companies to act on several at once. */
    selection: IdSelection | null;
}) {
    return (
        <Table>
            <TableCaption className="sr-only">
                Empresas, de la más reciente a la más antigua
            </TableCaption>
            <TableHeader>
                <TableRow>
                    {selection !== null && (
                        <TableHead className="w-px">
                            <Checkbox
                                checked={selection.allChosen}
                                onCheckedChange={(checked) =>
                                    selection.toggleAll(checked === true)
                                }
                                aria-label="Elegir todas las empresas de esta página"
                                className="touch-hit"
                            />
                        </TableHead>
                    )}
                    <TableHead>Empresa</TableHead>
                    <TableHead className="max-md:hidden">Estado</TableHead>
                    <TableHead className="hidden md:table-cell">
                        Dueño
                    </TableHead>
                    <TableHead className="hidden lg:table-cell">
                        Asientos
                    </TableHead>
                    <TableHead className="hidden 2xl:table-cell">
                        Responsable
                    </TableHead>
                    <TableHead className="hidden lg:table-cell">
                        Último acceso
                    </TableHead>
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
                        selection={selection}
                    />
                ))}
            </TableBody>
        </Table>
    );
}
