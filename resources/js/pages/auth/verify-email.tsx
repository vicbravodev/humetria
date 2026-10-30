import { Form, Head, Link } from '@inertiajs/react';
import { MailCheck } from 'lucide-react';
import AuthHeading from '@/components/auth-heading';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

export default function VerifyEmail({ status }: { status?: string }) {
    return (
        <>
            <Head title="Verificar correo" />

            <AuthHeading icon={MailCheck} title="Verifica tu correo">
                Abre el enlace que te enviamos por correo para verificar tu
                dirección.
            </AuthHeading>

            {status === 'verification-link-sent' && (
                <p
                    role="status"
                    className="rounded-xl border px-4 py-3.5 text-sm font-medium text-primary"
                >
                    Te enviamos un nuevo enlace de verificación al correo con el
                    que te registraste.
                </p>
            )}

            <Form {...send.form()} className="flex flex-wrap gap-2.5">
                {({ processing }) => (
                    <>
                        <Button variant="outline" disabled={processing}>
                            {processing && <Spinner />}
                            Reenviar correo de verificación
                        </Button>
                        <Button variant="ghost" asChild>
                            <Link href={logout()}>Cerrar sesión</Link>
                        </Button>
                    </>
                )}
            </Form>
        </>
    );
}
