import { Link } from '@inertiajs/react';
import { Eye, Pencil } from 'lucide-react';
import StackedTable from '@/components/humetria/stacked-table';
import { Button } from '@/components/ui/button';
import {
    TableBody,
    TableCaption,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { summarize } from '@/lib/permission-areas';
import type { PermissionArea } from '@/lib/permission-areas';
import { countOf } from '@/lib/plural';
import { edit } from '@/routes/tenant/roles';
import type { CompanyRoleRow } from '@/types';

/**
 * The company's own roles of one page (board Roles 7c): what each is called and where it came from, what it lets do in one
 * sentence, who holds it (and the invitations still open that name it, which keep it from being deleted). Support reads it,
 * so its link says «Ver».
 */
export default function RoleList({
    roles,
    areas,
    canManage,
}: {
    roles: CompanyRoleRow[];
    /** The areas the plan has: the sentence speaks only of those. */
    areas: PermissionArea[];
    canManage: boolean;
}) {
    const tenantSlug = useTenantSlug();

    return (
        <StackedTable>
            <TableCaption className="sr-only">
                Roles propios de la empresa
            </TableCaption>
            <TableHeader>
                <TableRow>
                    <TableHead>Rol</TableHead>
                    <TableHead>Qué puede hacer</TableHead>
                    <TableHead>Personas</TableHead>
                    <TableHead>
                        <span className="sr-only">Acciones</span>
                    </TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {roles.map((role) => (
                    <TableRow key={role.id}>
                        <TableCell className="whitespace-normal max-md:w-full md:w-1/4">
                            <div className="flex flex-col">
                                <span className="font-medium">
                                    {role.label}
                                </span>
                                <span className="text-caption text-muted-foreground">
                                    {role.based_on === null
                                        ? 'Desde cero'
                                        : `Basado en ${role.based_on}`}
                                </span>
                            </div>
                        </TableCell>
                        <TableCell className="text-sm text-pretty whitespace-normal">
                            {summarize({
                                areas,
                                held: role.permissions,
                                subject: '',
                            })}
                        </TableCell>
                        <TableCell className="text-sm whitespace-normal text-muted-foreground tabular-nums md:min-w-28">
                            {countOf(role.people, 'persona', 'personas')}
                            {role.invitations > 0 &&
                                ` · ${countOf(role.invitations, 'invitación pendiente', 'invitaciones pendientes')}`}
                        </TableCell>
                        <TableCell className="text-right md:w-px">
                            <Button variant="outline" size="sm" asChild>
                                <Link
                                    href={edit({
                                        tenant: tenantSlug,
                                        role: role.id,
                                    })}
                                    aria-label={`${canManage ? 'Editar' : 'Ver'} ${role.label}`}
                                >
                                    {canManage ? (
                                        <Pencil aria-hidden />
                                    ) : (
                                        <Eye aria-hidden />
                                    )}
                                    {canManage ? 'Editar' : 'Ver'}
                                </Link>
                            </Button>
                        </TableCell>
                    </TableRow>
                ))}
            </TableBody>
        </StackedTable>
    );
}
