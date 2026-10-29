import { Head } from '@inertiajs/react';
import AcceptInvitationForm from '@/components/tenancy/accept-invitation-form';
import InvitationNotice from '@/components/tenancy/invitation-notice';
import type { InvitationPage } from '@/types/tenancy';

export default function AcceptInvitation(props: InvitationPage) {
    return (
        <>
            <Head title="Acepta tu invitación" />
            {props.state === 'new_account' ? (
                <AcceptInvitationForm
                    token={props.token}
                    email={props.email}
                    name={props.name}
                    passwordRules={props.passwordRules}
                />
            ) : (
                <InvitationNotice {...props} />
            )}
        </>
    );
}

AcceptInvitation.layout = ({ invited_as }: InvitationPage) => ({
    title: 'Acepta tu invitación',
    description: invited_as ?? '',
});
