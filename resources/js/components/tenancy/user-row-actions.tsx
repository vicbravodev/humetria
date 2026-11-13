import { KeyRound, ShieldCheck, ShieldOff, ShieldX } from 'lucide-react';
import { useState } from 'react';
import ConfirmDialog from '@/components/humetria/confirm-dialog';
import RowMenu from '@/components/humetria/row-menu';
import { actionCopy, userActions } from '@/lib/platform-users';
import type { UserAction } from '@/lib/platform-users';
import { passwordReset } from '@/routes/platform-users';
import {
    destroy as removeSupport,
    store as grantSupport,
} from '@/routes/platform-users/support';
import { destroy as removeTwoFactor } from '@/routes/platform-users/two-factor';
import type { PlatformUserRow } from '@/types';

const ICONS = {
    reset: KeyRound,
    two_factor: ShieldOff,
    grant_support: ShieldCheck,
    revoke_support: ShieldX,
};

const FORMS = {
    reset: passwordReset,
    two_factor: removeTwoFactor,
    grant_support: grantSupport,
    revoke_support: removeSupport,
};

/** Behind the "…" of a user: help them back in or change their support role, each after confirming who it is. */
export default function UserRowActions({ user }: { user: PlatformUserRow }) {
    const [asking, setAsking] = useState<UserAction | null>(null);
    const copy = asking === null ? null : actionCopy(asking, user);

    return (
        <>
            <RowMenu
                label={`Acciones de ${user.name}`}
                items={userActions(user).map((action) => ({
                    label: actionCopy(action, user).menu,
                    icon: ICONS[action],
                    onSelect: () => setAsking(action),
                    destructive: actionCopy(action, user).destructive,
                }))}
            />
            {asking !== null && copy !== null && (
                <ConfirmDialog
                    open
                    onOpenChange={() => setAsking(null)}
                    title={copy.title}
                    description={copy.description}
                    confirmLabel={copy.confirm}
                    destructive={copy.destructive}
                    preserveScroll
                    transform={() =>
                        asking === 'grant_support'
                            ? { confirmed_client_member: '1' }
                            : {}
                    }
                    form={FORMS[asking].form({ user: user.id })}
                />
            )}
        </>
    );
}
