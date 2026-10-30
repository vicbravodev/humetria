import { Head } from '@inertiajs/react';
import type { Props as ManagePasskeysProps } from '@/components/passkeys/manage-passkeys';
import ManagePasskeys from '@/components/passkeys/manage-passkeys';
import UpdatePasswordForm from '@/components/settings/update-password-form';
import type { Props as ManageTwoFactorProps } from '@/components/two-factor/manage-two-factor';
import ManageTwoFactor from '@/components/two-factor/manage-two-factor';

// oxfmt-ignore
type Props = {
    passwordRules: string;
} & ManagePasskeysProps &
    ManageTwoFactorProps;

export default function Security(props: Props) {
    return (
        <>
            <Head title="Seguridad" />

            <UpdatePasswordForm passwordRules={props.passwordRules} />

            <ManageTwoFactor
                canManageTwoFactor={props.canManageTwoFactor}
                requiresConfirmation={props.requiresConfirmation}
                twoFactorEnabled={props.twoFactorEnabled}
            />

            <ManagePasskeys
                canManagePasskeys={props.canManagePasskeys}
                passkeys={props.passkeys}
            />
        </>
    );
}

Security.layout = {
    breadcrumbs: [{ title: 'Cuenta' }, { title: 'Configuración' }],
};
