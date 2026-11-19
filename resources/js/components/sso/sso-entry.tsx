import { router, usePage } from '@inertiajs/react';
import { Building2 } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/humetria/input-error';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { discover } from '@/routes/sso';

type Props = { email: string };

/**
 * Another way in of the login: the work email already typed above decides which company's provider to go to. Whatever goes
 * wrong (here or on the way back) says the same thing, so nothing tells who has single sign-on.
 */
export default function SsoEntry({ email }: Props) {
    const refused = usePage().props.errors['sso'];
    const [processing, setProcessing] = useState(false);
    const [missing, setMissing] = useState(false);

    const go = () => {
        if (email.trim() === '') {
            setMissing(true);

            return;
        }

        setMissing(false);
        router.post(
            discover.url(),
            { email },
            {
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <div className="grid gap-2">
            <Button
                type="button"
                variant="outline"
                className="w-full"
                onClick={go}
                disabled={processing}
            >
                {processing ? <Spinner /> : <Building2 aria-hidden />}
                Continuar con SSO
            </Button>
            <InputError
                message={
                    missing
                        ? 'Escribe arriba tu correo de trabajo para continuar con el SSO de tu empresa.'
                        : refused
                }
            />
        </div>
    );
}
