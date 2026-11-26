import { useState } from 'react';
import Initials from '@/components/humetria/initials';
import UserActionDialog from '@/components/tenancy/user-action-dialog';
import { Button } from '@/components/ui/button';
import { helpActions, helpLine } from '@/lib/platform-users';
import type { UserAction } from '@/lib/platform-users';
import type { PlatformUserRow } from '@/types';

const LABELS: Partial<Record<UserAction, string>> = {
    reset: 'Enviar enlace de contraseña',
    two_factor: 'Quitar 2FA…',
};

/** A person of the quick help (tablero Equipo): who, why they may not get in, and the two ways to help, each confirmed. */
export default function UsersHelpRow({ user }: { user: PlatformUserRow }) {
    const [asking, setAsking] = useState<UserAction | null>(null);

    return (
        <li className="flex flex-wrap items-center gap-x-3 gap-y-2 border-t px-6 py-3.5 first:border-t-0">
            <Initials name={user.name} />
            <div className="min-w-0 flex-1 basis-48">
                <p className="font-medium">{user.name}</p>
                <p className="text-sm break-words text-muted-foreground">
                    {helpLine(user)}
                </p>
            </div>
            {helpActions(user).map((action) => (
                <Button
                    key={action}
                    variant="outline"
                    size="sm"
                    aria-label={`${LABELS[action] ?? action}: ${user.name}`}
                    onClick={() => setAsking(action)}
                >
                    {LABELS[action]}
                </Button>
            ))}
            <UserActionDialog
                user={user}
                asking={asking}
                onClose={() => setAsking(null)}
            />
        </li>
    );
}
