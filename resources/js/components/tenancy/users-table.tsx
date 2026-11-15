import UserRowView from '@/components/tenancy/user-row';
import {
    Table,
    TableBody,
    TableCaption,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { PlatformUserRow } from '@/types';

export default function UsersTable({ users }: { users: PlatformUserRow[] }) {
    return (
        <Table>
            <TableCaption className="sr-only">
                Usuarios de la plataforma, del más reciente al más antiguo
            </TableCaption>
            <TableHeader>
                <TableRow>
                    <TableHead>Persona</TableHead>
                    <TableHead>Rol de plataforma</TableHead>
                    <TableHead className="hidden md:table-cell">
                        Empresas
                    </TableHead>
                    <TableHead className="hidden md:table-cell">
                        Verificación en dos pasos
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
                {users.map((user) => (
                    <UserRowView key={user.id} user={user} />
                ))}
            </TableBody>
        </Table>
    );
}
