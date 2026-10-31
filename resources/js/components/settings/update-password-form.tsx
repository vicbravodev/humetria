import { Form } from '@inertiajs/react';
import { useRef, useState } from 'react';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import NewPasswordFields from '@/components/settings/new-password-fields';
import { Button } from '@/components/ui/button';

/** Changing your password: the current one, then the new one twice, ticking off what it still needs. */
export default function UpdatePasswordForm({
    passwordRules,
}: {
    passwordRules: string;
}) {
    const passwordInput = useRef<HTMLInputElement>(null);
    const currentPasswordInput = useRef<HTMLInputElement>(null);
    const [password, setPassword] = useState('');

    return (
        <section
            aria-labelledby="password-title"
            className="flex flex-col gap-3.5"
        >
            <div className="flex flex-col">
                <h2
                    id="password-title"
                    className="text-lg leading-[26px] font-semibold"
                >
                    Contraseña
                </h2>
                <p className="text-sm text-muted-foreground">
                    Usa una contraseña larga y que no uses en otro lado.
                </p>
            </div>
            <Form
                {...SecurityController.update.form()}
                options={{ preserveScroll: true }}
                resetOnError={[
                    'password',
                    'password_confirmation',
                    'current_password',
                ]}
                resetOnSuccess
                onSuccess={() => setPassword('')}
                onError={(errors) => {
                    if (errors['password']) {
                        passwordInput.current?.focus();
                    }

                    if (errors['current_password']) {
                        currentPasswordInput.current?.focus();
                    }
                }}
                className="flex flex-col gap-4"
            >
                {({ errors, processing }) => (
                    <>
                        <div className="grid grid-cols-[repeat(auto-fit,minmax(240px,1fr))] items-start gap-4">
                            <NewPasswordFields
                                errors={errors}
                                passwordRules={passwordRules}
                                password={password}
                                onPasswordChange={setPassword}
                                currentPasswordInput={currentPasswordInput}
                                passwordInput={passwordInput}
                            />
                        </div>
                        <Button
                            disabled={processing}
                            data-test="update-password-button"
                            className="self-start"
                        >
                            Guardar contraseña
                        </Button>
                    </>
                )}
            </Form>
        </section>
    );
}
