import { Form, Link } from '@inertiajs/react';
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
                <p className="text-center text-sm">
                    Este enlace ya no es válido. Pide uno nuevo al administrador
                    de Humetría.
                </p>
            );
        case 'sign_in':
            return (
                <div className="grid gap-6">
                    <p className="text-center text-sm">
                        Ya tienes una cuenta en Humetría. Inicia sesión para
                        unirte a {props.trade_name}.
                    </p>
                    <Button asChild className="w-full">
                        <Link href={login()}>Iniciar sesión</Link>
                    </Button>
                </div>
            );
        case 'confirm':
            return (
                <Form {...accept.form(props.token)} className="grid gap-6">
                    {({ processing }) => (
                        <>
                            <p className="text-center text-sm">
                                Únete a {props.trade_name}
                            </p>
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
                    className="grid gap-6"
                >
                    {({ processing }) => (
                        <>
                            <p className="text-center text-sm">
                                Esta invitación es para {props.email}. Cierra
                                sesión para continuar.
                            </p>
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
