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
import type { TenantPeek } from '@/components/tenancy/tenant-quick-view';
import type { TenantRow } from '@/types';

export default function TenantsTable({
    tenants,
    activationMinDate,
    selection,
    peek,
}: {
    tenants: TenantRow[];
    activationMinDate: string;
    /** Only the superadmin chooses companies to act on several at once. */
    selection: IdSelection | null;
    /** On a wide screen, the company whose quick view is docked beside the table, and how to choose another. */
    peek: TenantPeek | null;
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
                    <TableHead className="hidden lg:table-cell">Plan</TableHead>
                    <TableHead className="hidden lg:table-cell">
                        Asientos
                    </TableHead>
                    <TableHead className="hidden text-right xl:table-cell">
                        Al mes
                    </TableHead>
                    {/* With the quick view docked (1600 px and up) these two live in it. */}
                    <TableHead className="hidden min-[1400px]:table-cell min-[1600px]:hidden">
                        Renueva
                    </TableHead>
                    <TableHead className="hidden min-[1500px]:table-cell min-[1600px]:hidden">
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
                        peek={peek}
                    />
                ))}
            </TableBody>
        </Table>
    );
}
