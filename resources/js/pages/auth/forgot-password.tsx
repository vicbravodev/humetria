import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useState } from 'react';
import ResetLinkForm from '@/components/auth/reset-link-form';
import ResetLinkSent from '@/components/auth/reset-link-sent';
import { login } from '@/routes';

export default function ForgotPassword({ status }: { status: string | null }) {
    const [email, setEmail] = useState('');
    const [changingEmail, setChangingEmail] = useState(false);

    return (
        <>
            <Head title="Recuperar contraseña" />

            <Link
                href={login()}
                className="touch-hit flex items-center gap-1.5 self-start rounded-md text-sm font-medium text-muted-foreground hover:text-foreground"
            >
                <ArrowLeft aria-hidden className="size-4" />
                Volver a iniciar sesión
            </Link>

            {status !== null && !changingEmail ? (
                <ResetLinkSent
                    email={email}
                    onChangeEmail={() => setChangingEmail(true)}
                />
            ) : (
                <ResetLinkForm
                    email={email}
                    onEmailChange={setEmail}
                    onSent={() => setChangingEmail(false)}
                />
            )}
        </>
    );
}
