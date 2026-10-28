import { Head } from '@inertiajs/react';
import AcceptInvitationForm from '@/components/tenancy/accept-invitation-form';
import InvitationNotice from '@/components/tenancy/invitation-notice';

type Props = { token: string } & (
    | { state: 'invalid'; trade_name: null; email: null; name: null }
    | { state: 'new_account'; trade_name: string; email: string; name: string }
);

export default function AcceptInvitation(props: Props) {
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
                <InvitationNotice />
            )}
        </>
    );
}

AcceptInvitation.layout = ({ trade_name }: Props) => ({
    title: 'Acepta tu invitación',
    description:
        trade_name === null ? '' : `Te invitaron como owner de ${trade_name}`,
});
