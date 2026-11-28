import { KeyRound, ShieldCheck, ShieldOff, ShieldX } from 'lucide-react';
import { useState } from 'react';
import RowMenu from '@/components/humetria/row-menu';
import UserManageMenu from '@/components/tenancy/user-manage-menu';
import UserActionDialog from '@/components/tenancy/user-action-dialog';
import { actionCopy, userActions } from '@/lib/platform-users';
import type { UserAction } from '@/lib/platform-users';
import type { PlatformUserRow } from '@/types';

const ICONS = {
    reset: KeyRound,
    two_factor: ShieldOff,
    grant_support: ShieldCheck,
    revoke_support: ShieldX,
};

/**
 * Behind the "…" of a user, or their «Gestionar» on the team (tablero Equipo): help them back in or change their support
 * role, each after confirming who it is.
 */
export default function UserRowActions({
    user,
    manage = false,
}: {
    user: PlatformUserRow;
    manage?: boolean;
}) {
    const [asking, setAsking] = useState<UserAction | null>(null);
    const items = userActions(user).map((action) => ({
        label: actionCopy(action, user).menu,
        icon: ICONS[action],
        onSelect: () => setAsking(action),
        destructive: actionCopy(action, user).destructive,
    }));

    return (
        <>
            {manage ? (
                <UserManageMenu name={user.name} items={items} />
            ) : (
                <RowMenu label={`Acciones de ${user.name}`} items={items} />
            )}
            <UserActionDialog
                user={user}
                asking={asking}
                onClose={() => setAsking(null)}
            />
        </>
    );
}
