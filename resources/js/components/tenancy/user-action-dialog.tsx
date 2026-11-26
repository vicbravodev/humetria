import ConfirmDialog from '@/components/humetria/confirm-dialog';
import { actionCopy } from '@/lib/platform-users';
import type { UserAction } from '@/lib/platform-users';
import { passwordReset } from '@/routes/platform-users';
import {
    destroy as removeSupport,
    store as grantSupport,
} from '@/routes/platform-users/support';
import { destroy as removeTwoFactor } from '@/routes/platform-users/two-factor';
import type { PlatformUserRow } from '@/types';

const FORMS = {
    reset: passwordReset,
    two_factor: removeTwoFactor,
    grant_support: grantSupport,
    revoke_support: removeSupport,
};

/** The confirmation of an action on a person, naming who it is, before it goes out; nothing when no action is asked. */
export default function UserActionDialog({
    user,
    asking,
    onClose,
}: {
    user: PlatformUserRow;
    asking: UserAction | null;
    onClose: () => void;
}) {
    if (asking === null) {
        return null;
    }

    const copy = actionCopy(asking, user);

    return (
        <ConfirmDialog
            open
            onOpenChange={onClose}
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
    );
}
