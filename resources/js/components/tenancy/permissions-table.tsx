import PermissionHeld from '@/components/tenancy/permission-held';
import {
    Table,
    TableBody,
    TableCaption,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { PlatformPermissionRow } from '@/types';

/** What each platform role may do, read only: it comes from the code's own permissions, not from a list kept by hand. */
export default function PermissionsTable({
    permissions,
}: {
    permissions: PlatformPermissionRow[];
}) {
    return (
        <Table>
            <TableCaption className="caption-top pb-3 text-left text-meta text-muted-foreground">
                Se lee de los permisos del código: lo que dice esta tabla es lo
                que la plataforma deja hacer. Cambiar un permiso es un cambio de
                código.
            </TableCaption>
            <TableHeader>
                <TableRow>
                    <TableHead>Acción</TableHead>
                    <TableHead className="w-28">Soporte</TableHead>
                    <TableHead className="w-28">Superadmin</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {permissions.map((permission) => (
                    <TableRow key={permission.ability}>
                        <TableCell className="whitespace-normal">
                            {permission.label}
                        </TableCell>
                        <TableCell>
                            <PermissionHeld held={permission.support} />
                        </TableCell>
                        <TableCell>
                            <PermissionHeld held={permission.superadmin} />
                        </TableCell>
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}
