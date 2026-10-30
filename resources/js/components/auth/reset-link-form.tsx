import { Form } from '@inertiajs/react';
import AuthHeading from '@/components/auth-heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { email as sendLink } from '@/routes/password';

/** Asks for the email to send a reset link to. The email stays with the page, which says where the link went. */
export default function ResetLinkForm({
    email,
    onEmailChange,
    onSent,
}: {
    email: string;
    onEmailChange: (email: string) => void;
    onSent: () => void;
}) {
    return (
        <>
            <AuthHeading title="¿Olvidaste tu contraseña?">
                Escribe tu correo y te enviamos un enlace para crear una nueva.
            </AuthHeading>

            <Form
                {...sendLink.form()}
                options={{ preserveState: true }}
                onSuccess={onSent}
                className="flex flex-col gap-4"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-1.5">
                            <Label htmlFor="email">Correo electrónico</Label>
                            <Input
                                id="email"
                                type="email"
                                name="email"
                                required
                                autoFocus
                                autoComplete="email"
                                placeholder="nombre@empresa.mx"
                                value={email}
                                onChange={(event) =>
                                    onEmailChange(event.target.value)
                                }
                            />
                            <InputError message={errors['email']} />
                        </div>

                        <Button
                            type="submit"
                            className="w-full"
                            disabled={processing}
                            data-test="email-password-reset-link-button"
                        >
                            {processing && <Spinner />}
                            Enviar enlace
                        </Button>
                    </>
                )}
            </Form>
        </>
    );
}
