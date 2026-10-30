import { Form, Head } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import {
    index as confirmOptions,
    store as confirmStore,
} from '@/actions/Laravel/Passkeys/Http/Controllers/PasskeyConfirmationController';
import AuthHeading from '@/components/auth-heading';
import InputError from '@/components/input-error';
import PasskeyVerify from '@/components/passkeys/passkey-verify';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/password/confirm';

export default function ConfirmPassword() {
    return (
        <>
            <Head title="Confirmar contraseña" />

            <AuthHeading icon={ShieldCheck} title="Confirma que eres tú">
                Vas a cambiar la seguridad de tu cuenta. Confirma tu contraseña
                para continuar.
            </AuthHeading>

            <PasskeyVerify
                routes={{
                    options: confirmOptions(),
                    submit: confirmStore(),
                }}
                copy={{
                    label: 'Confirmar con llave de acceso',
                    loading: 'Confirmando…',
                    separator: 'o con tu contraseña',
                }}
                primary
            />

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                className="flex flex-col gap-4"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-1.5">
                            <Label htmlFor="password">Contraseña</Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                required
                                autoComplete="current-password"
                                autoFocus
                            />
                            <InputError message={errors['password']} />
                        </div>

                        <Button
                            variant="outline"
                            className="w-full"
                            disabled={processing}
                            data-test="confirm-password-button"
                        >
                            {processing && <Spinner />}
                            Confirmar con contraseña
                        </Button>
                    </>
                )}
            </Form>
        </>
    );
}
