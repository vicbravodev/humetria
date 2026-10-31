import { Form, Head } from '@inertiajs/react';
import { KeySquare, Smartphone } from 'lucide-react';
import { useState } from 'react';
import AuthHeading from '@/components/auth-heading';
import InputError from '@/components/input-error';
import TwoFactorChallengeCode from '@/components/two-factor/two-factor-challenge-code';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { store } from '@/routes/two-factor/login';

const MODES = {
    code: {
        icon: Smartphone,
        title: 'Código de verificación',
        description:
            'Escribe el código de verificación que te da tu aplicación de autenticación.',
        toggleText: 'entrar con un código de recuperación',
    },
    recovery: {
        icon: KeySquare,
        title: 'Código de recuperación',
        description:
            'Confirma el acceso a tu cuenta escribiendo uno de tus códigos de recuperación de emergencia.',
        toggleText: 'entrar con un código de verificación',
    },
};

export default function TwoFactorChallenge() {
    const [showRecoveryInput, setShowRecoveryInput] = useState<boolean>(false);
    const [code, setCode] = useState<string>('');
    const mode = showRecoveryInput ? MODES.recovery : MODES.code;

    const toggleRecoveryMode = (clearErrors: () => void): void => {
        setShowRecoveryInput(!showRecoveryInput);
        clearErrors();
        setCode('');
    };

    return (
        <>
            <Head title="Verificación en dos pasos" />

            <AuthHeading icon={mode.icon} title={mode.title}>
                {mode.description}
            </AuthHeading>

            <Form
                {...store.form()}
                className="flex flex-col gap-4"
                resetOnError
                resetOnSuccess={!showRecoveryInput}
            >
                {({ errors, processing, clearErrors }) => (
                    <>
                        {showRecoveryInput ? (
                            <div className="grid gap-1.5">
                                <Input
                                    name="recovery_code"
                                    type="text"
                                    aria-label="Código de recuperación"
                                    placeholder="Escribe tu código de recuperación"
                                    autoFocus
                                    required
                                />
                                <InputError message={errors['recovery_code']} />
                            </div>
                        ) : (
                            <TwoFactorChallengeCode
                                code={code}
                                onChange={setCode}
                                disabled={processing}
                                error={errors['code']}
                            />
                        )}

                        <Button
                            type="submit"
                            className="w-full"
                            disabled={processing}
                        >
                            Continuar
                        </Button>

                        <p className="text-sm text-muted-foreground">
                            O también puedes{' '}
                            <button
                                type="button"
                                className="cursor-pointer rounded-sm font-medium text-primary underline-offset-4 hover:underline"
                                onClick={() => toggleRecoveryMode(clearErrors)}
                            >
                                {mode.toggleText}
                            </button>
                        </p>
                    </>
                )}
            </Form>
        </>
    );
}
