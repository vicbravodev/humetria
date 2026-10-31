import { Form, Head, Link } from '@inertiajs/react';
import AuthHeading from '@/components/auth-heading';
import InputError from '@/components/input-error';
import PasskeyVerify from '@/components/passkeys/passkey-verify';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { home } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

type Props = {
    status?: string;
    canResetPassword: boolean;
};

export default function Login({ status, canResetPassword }: Props) {
    return (
        <>
            <Head title="Iniciar sesión" />

            <AuthHeading title="Inicia sesión">
                Entra a tu cuenta de Humetría.
            </AuthHeading>

            {status && (
                <p role="status" className="text-sm font-medium text-primary">
                    {status}
                </p>
            )}

            <PasskeyVerify />

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
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
                            />
                            <InputError message={errors['email']} />
                        </div>

                        <div className="grid gap-1.5">
                            <div className="flex items-center justify-between">
                                <Label htmlFor="password">Contraseña</Label>
                                {canResetPassword && (
                                    <Link
                                        href={request()}
                                        className="touch-hit text-sm font-medium text-primary underline-offset-4 hover:underline"
                                    >
                                        ¿La olvidaste?
                                    </Link>
                                )}
                            </div>
                            <PasswordInput
                                id="password"
                                name="password"
                                required
                                autoComplete="current-password"
                            />
                            <InputError message={errors['password']} />
                        </div>

                        <Label className="flex min-h-11 cursor-pointer items-center gap-2.5 font-normal">
                            <Checkbox id="remember" name="remember" />
                            Mantener la sesión en este equipo
                        </Label>

                        <Button
                            type="submit"
                            className="w-full"
                            disabled={processing}
                            data-test="login-button"
                        >
                            {processing && <Spinner />}
                            Iniciar sesión
                        </Button>
                    </>
                )}
            </Form>

            <p className="text-sm text-muted-foreground">
                ¿Tu empresa aún no usa Humetría?{' '}
                <a
                    href={`${home.url()}#agenda-una-demo`}
                    className="font-medium text-primary underline-offset-4 hover:underline"
                >
                    Agenda una demo
                </a>
            </p>
        </>
    );
}
