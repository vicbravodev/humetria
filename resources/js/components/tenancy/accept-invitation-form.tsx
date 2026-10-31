import { Form } from '@inertiajs/react';
import { Lock } from 'lucide-react';
import AuthHeading from '@/components/auth-heading';
import InputError from '@/components/input-error';
import NewPasswordField from '@/components/new-password-field';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { accept } from '@/routes/invitations';

type Props = {
    token: string;
    email: string;
    name: string;
    passwordRules: string;
};

/** Creates the invited person's account: the email is the invitation's and cannot change. */
export default function AcceptInvitationForm({
    token,
    email,
    name,
    passwordRules,
}: Props) {
    return (
        <>
            <AuthHeading title="Crea tu cuenta">
                Con este correo entrarás a Humetría.
            </AuthHeading>

            <Form
                {...accept.form(token)}
                resetOnSuccess={['password', 'password_confirmation']}
                className="flex flex-col gap-4"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-1.5">
                            <Label htmlFor="email">Correo electrónico</Label>
                            <div className="relative">
                                <Lock
                                    aria-hidden
                                    className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                />
                                <Input
                                    id="email"
                                    type="email"
                                    autoComplete="email"
                                    value={email}
                                    readOnly
                                    className="bg-muted pl-9 text-muted-foreground"
                                />
                            </div>
                        </div>

                        <div className="grid gap-1.5">
                            <Label htmlFor="name">Tu nombre</Label>
                            <Input
                                id="name"
                                name="name"
                                defaultValue={name}
                                required
                                maxLength={255}
                                autoComplete="name"
                            />
                            <InputError message={errors['name']} />
                        </div>

                        <NewPasswordField
                            label="Contraseña"
                            passwordRules={passwordRules}
                            error={errors['password']}
                        />

                        <div className="grid gap-1.5">
                            <Label htmlFor="password_confirmation">
                                Repite la contraseña
                            </Label>
                            <PasswordInput
                                id="password_confirmation"
                                name="password_confirmation"
                                required
                                autoComplete="new-password"
                                passwordrules={passwordRules}
                            />
                            <InputError
                                message={errors['password_confirmation']}
                            />
                        </div>

                        <Button
                            type="submit"
                            className="w-full"
                            disabled={processing}
                        >
                            {processing && <Spinner />}
                            Crear cuenta y entrar
                        </Button>
                    </>
                )}
            </Form>
        </>
    );
}
