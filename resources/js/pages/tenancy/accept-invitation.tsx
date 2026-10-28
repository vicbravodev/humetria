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
                />
            ) : (
                <InvitationNotice {...props} />
            )}
        </>
    );
}

AcceptInvitation.layout = ({ trade_name }: InvitationPage) => ({
    title: 'Acepta tu invitación',
    description:
        trade_name === null ? '' : `Te invitaron como owner de ${trade_name}`,
});
