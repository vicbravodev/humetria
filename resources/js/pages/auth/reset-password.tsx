import { Form, Head } from '@inertiajs/react';
import AuthHeading from '@/components/auth-heading';
import InputError from '@/components/input-error';
import NewPasswordField from '@/components/new-password-field';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { update } from '@/routes/password';

type Props = {
    token: string;
    email: string;
    passwordRules: string;
};

export default function ResetPassword({ token, email, passwordRules }: Props) {
    return (
        <>
            <Head title="Restablecer contraseña" />

            <AuthHeading title="Crea una contraseña nueva">
                Para {email}.
            </AuthHeading>

            <Form
                {...update.form()}
                transform={(data) => ({ ...data, token, email })}
                resetOnSuccess={['password', 'password_confirmation']}
                className="flex flex-col gap-4"
            >
                {({ processing, errors }) => (
                    <>
                        <input type="hidden" name="email" value={email} />
                        <InputError message={errors['email']} />

                        <NewPasswordField
                            label="Contraseña nueva"
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
                            data-test="reset-password-button"
                        >
                            {processing && <Spinner />}
                            Guardar contraseña
                        </Button>
                    </>
                )}
            </Form>
        </>
    );
}
