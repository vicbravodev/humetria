import Initials from '@/components/humetria/initials';
import UserRowActions from '@/components/tenancy/user-row-actions';
import { Badge } from '@/components/ui/badge';
import { TableCell, TableRow } from '@/components/ui/table';
import { platformRoleLabel } from '@/lib/platform-users';
import { countOf } from '@/lib/plural';
import type { PlatformUserRow } from '@/types';

/** A person of Humetría's team (tablero Equipo): who, their role, two-factor, last sign-in and their entries as support. */
export default function UsersTeamRow({ user }: { user: PlatformUserRow }) {
    return (
        <TableRow>
            <TableCell className="whitespace-normal md:min-w-56">
                <div className="flex items-center gap-3">
                    <Initials name={user.name} />
                    <div className="flex min-w-0 flex-col">
                        <span className="font-medium">{user.name}</span>
                        <span className="text-caption break-all text-muted-foreground">
                            {user.email}
                        </span>
                    </div>
                </div>
            </TableCell>
            <TableCell>
                <Badge
                    variant={
                        user.platformRole === 'superadmin'
                            ? 'default'
                            : 'secondary'
                    }
                >
                    {platformRoleLabel(user.platformRole)}
                </Badge>
            </TableCell>
            <TableCell>
                <Badge variant={user.twoFactor ? 'success' : 'warning'}>
                    {user.twoFactor ? 'Activa' : 'Sin 2FA'}
                </Badge>
            </TableCell>
            <TableCell className="text-muted-foreground tabular-nums">
                {user.lastLogin ?? 'Nunca'}
            </TableCell>
            <TableCell className="tabular-nums">
                {user.supportEntries === 0 ? (
                    <span className="text-muted-foreground">Ninguna</span>
                ) : (
                    countOf(user.supportEntries, 'entrada', 'entradas')
                )}
            </TableCell>
            <TableCell className="w-px text-right">
                <UserRowActions user={user} manage />
            </TableCell>
        </TableRow>
    );
}
