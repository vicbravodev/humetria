import UsersTeamRow from '@/components/tenancy/users-team-row';
import {
    Table,
    TableBody,
    TableCaption,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { PlatformUserRow } from '@/types';

/** Humetría's team (tablero Equipo), wider than a phone: it scrolls sideways inside its card there. */
export default function UsersTeamTable({
    users,
}: {
    users: PlatformUserRow[];
}) {
    return (
        <Table className="min-w-[640px] text-sm [&_td]:px-3 [&_th]:px-3">
            <TableCaption className="sr-only">
                Equipo de Humetría, del más reciente al más antiguo
            </TableCaption>
            <TableHeader>
                <TableRow>
                    <TableHead>Persona</TableHead>
                    <TableHead>Rol</TableHead>
                    <TableHead>2FA</TableHead>
                    <TableHead>Último acceso</TableHead>
                    <TableHead className="whitespace-normal">
                        Entró como soporte (30 d)
                    </TableHead>
                    <TableHead>
                        <span className="sr-only">Acciones</span>
                    </TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {users.map((user) => (
                    <UsersTeamRow key={user.id} user={user} />
                ))}
            </TableBody>
        </Table>
    );
}
