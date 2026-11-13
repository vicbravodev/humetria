import { ShieldCheck } from 'lucide-react';
import Initials from '@/components/humetria/initials';
import UserRowActions from '@/components/tenancy/user-row-actions';
import { Badge } from '@/components/ui/badge';
import { TableCell, TableRow } from '@/components/ui/table';
import { platformRoleLabel } from '@/lib/platform-users';
import type { PlatformUserRow } from '@/types';

/** A person of the platform: who they are, their platform role, whether they use two-factor, and where they work. */
export default function UserRowView({ user }: { user: PlatformUserRow }) {
    return (
        <TableRow>
            <TableCell className="whitespace-normal max-md:px-2 md:min-w-60">
                <div className="flex items-center gap-2.5">
                    <Initials name={user.name} />
                    <div className="flex min-w-0 flex-col">
                        <span className="font-medium">{user.name}</span>
                        <span className="text-caption break-all text-muted-foreground">
                            {user.email}
                        </span>
                        {!user.verified && (
                            <span className="text-caption text-muted-foreground">
                                Correo sin verificar
                            </span>
                        )}
                    </div>
                </div>
            </TableCell>
            <TableCell className="whitespace-normal max-md:px-2">
                {user.platformRole === null ? (
                    <span className="text-muted-foreground">
                        {platformRoleLabel(null)}
                    </span>
                ) : (
                    <Badge variant="secondary">
                        {platformRoleLabel(user.platformRole)}
                    </Badge>
                )}
            </TableCell>
            <TableCell className="hidden whitespace-normal md:table-cell">
                {user.companies === '' ? (
                    <span className="text-muted-foreground">Sin empresas</span>
                ) : (
                    user.companies
                )}
            </TableCell>
            <TableCell className="hidden md:table-cell">
                {user.twoFactor ? (
                    <span className="flex items-center gap-1.5 text-sm">
                        <ShieldCheck
                            aria-hidden
                            className="size-4 text-primary"
                        />
                        Activa
                    </span>
                ) : (
                    <span className="text-muted-foreground">No</span>
                )}
            </TableCell>
            <TableCell className="hidden text-sm tabular-nums lg:table-cell">
                {user.lastLogin ?? (
                    <span className="text-muted-foreground">Nunca</span>
                )}
            </TableCell>
            <TableCell className="w-px text-right max-md:px-2">
                <UserRowActions user={user} />
            </TableCell>
        </TableRow>
    );
}
