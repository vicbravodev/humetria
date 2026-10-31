import { Form, Link } from '@inertiajs/react';
import { Link2Off } from 'lucide-react';
import AuthHeading from '@/components/auth-heading';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { accept, switchAccount } from '@/routes/invitations';
import type { InvitationPage } from '@/types/tenancy';

type Props = Exclude<InvitationPage, { state: 'new_account' }>;

/** What the invitation page says when it asks for no new account: the link is dead, or an existing account goes on. */
export default function InvitationNotice(props: Props) {
    switch (props.state) {
        case 'invalid':
            return (
                <AuthHeading
                    icon={Link2Off}
                    title="Este enlace ya no es válido"
                >
                    Venció, ya se usó o quien lo envió lo canceló. Pide uno
                    nuevo a quien te invitó.
                </AuthHeading>
            );
        case 'sign_in':
            return (
                <>
                    <AuthHeading title="Ya tienes una cuenta en Humetría">
                        Inicia sesión para unirte a {props.trade_name}.
                    </AuthHeading>
                    <Button asChild className="w-full">
                        <Link href={login()}>Iniciar sesión</Link>
                    </Button>
                </>
            );
        case 'confirm':
            return (
                <Form
                    {...accept.form(props.token)}
                    className="flex flex-col gap-7"
                >
                    {({ processing }) => (
                        <>
                            <AuthHeading title={`Únete a ${props.trade_name}`}>
                                Entrarás con tu cuenta de Humetría.
                            </AuthHeading>
                            <Button
                                type="submit"
                                className="w-full"
                                disabled={processing}
                            >
                                {processing && <Spinner />}
                                Aceptar invitación
                            </Button>
                        </>
                    )}
                </Form>
            );
        case 'other_account':
            return (
                <Form
                    {...switchAccount.form(props.token)}
                    className="flex flex-col gap-7"
                >
                    {({ processing }) => (
                        <>
                            <AuthHeading title="Entraste con otra cuenta">
                                Esta invitación es para {props.email}. Cierra
                                sesión para continuar.
                            </AuthHeading>
                            <Button
                                type="submit"
                                className="w-full"
                                disabled={processing}
                            >
                                {processing && <Spinner />}
                                Cerrar sesión y continuar
                            </Button>
                        </>
                    )}
                </Form>
            );
    }
}
