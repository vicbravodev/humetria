import { Form } from '@inertiajs/react';
import { Mail } from 'lucide-react';
import AuthHeading from '@/components/auth-heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { useCountdown } from '@/hooks/use-countdown';
import { formatCountdown } from '@/lib/countdown';
import { email as sendLink } from '@/routes/password';

// The password broker's own limits (config/auth.php, passwords.users): one link a minute, valid for an hour.
const RESEND_AFTER_SECONDS = 60;
const LINK_VALID_MINUTES = 60;

/** After asking for a reset link (pantalla: Olvide): where it went, what to do if it does not arrive, and a resend. */
export default function ResetLinkSent({
    email,
    onChangeEmail,
}: {
    email: string;
    onChangeEmail: () => void;
}) {
    const { left, restart } = useCountdown(RESEND_AFTER_SECONDS);

    return (
        <>
            <AuthHeading icon={Mail} title="Revisa tu correo">
                Si{' '}
                <strong className="font-semibold text-foreground">
                    {email === '' ? 'tu correo' : email}
                </strong>{' '}
                tiene cuenta, te llegará un enlace para crear una contraseña
                nueva. Vence en {LINK_VALID_MINUTES} minutos.
            </AuthHeading>

            <div className="flex flex-col gap-1 rounded-xl border px-4 py-3.5 text-sm">
                <span className="font-medium">¿No llega?</span>
                <span className="text-muted-foreground">
                    Busca en correo no deseado o espera un minuto antes de
                    pedirlo otra vez.
                </span>
            </div>

            <Form
                {...sendLink.form()}
                options={{ preserveState: true }}
                onSuccess={restart}
                className="flex flex-wrap gap-2.5"
            >
                {({ processing, errors }) => (
                    <>
                        <input type="hidden" name="email" value={email} />
                        <Button
                            type="submit"
                            variant="outline"
                            disabled={processing || left > 0}
                        >
                            {left > 0
                                ? `Enviar otra vez en ${formatCountdown(left)}`
                                : 'Enviar otra vez'}
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={onChangeEmail}
                        >
                            Usar otro correo
                        </Button>
                        <InputError
                            message={errors['email']}
                            className="w-full"
                        />
                    </>
                )}
            </Form>
        </>
    );
}
