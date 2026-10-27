import { Form, Head, setLayoutProps } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import InputError from '@/components/input-error';
import TwoFactorChallengeCode from '@/components/two-factor/two-factor-challenge-code';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { store } from '@/routes/two-factor/login';

export default function TwoFactorChallenge() {
    const [showRecoveryInput, setShowRecoveryInput] = useState<boolean>(false);
    const [code, setCode] = useState<string>('');

    const authConfigContent = useMemo<{
        title: string;
        description: string;
        toggleText: string;
    }>(() => {
        if (showRecoveryInput) {
            return {
                title: 'Código de recuperación',
                description:
                    'Confirma el acceso a tu cuenta escribiendo uno de tus códigos de recuperación de emergencia.',
                toggleText: 'entrar con un código de verificación',
            };
        }

        return {
            title: 'Código de verificación',
            description:
                'Escribe el código de verificación que te da tu aplicación de autenticación.',
            toggleText: 'entrar con un código de recuperación',
        };
    }, [showRecoveryInput]);

    setLayoutProps({
        title: authConfigContent.title,
        description: authConfigContent.description,
    });

    const toggleRecoveryMode = (clearErrors: () => void): void => {
        setShowRecoveryInput(!showRecoveryInput);
        clearErrors();
        setCode('');
    };

    return (
        <>
            <Head title="Verificación en dos pasos" />

            <div className="space-y-6">
                <Form
                    {...store.form()}
                    className="space-y-4"
                    resetOnError
                    resetOnSuccess={!showRecoveryInput}
                >
                    {({ errors, processing, clearErrors }) => (
                        <>
                            {showRecoveryInput ? (
                                <>
                                    <Input
                                        name="recovery_code"
                                        type="text"
                                        placeholder="Escribe tu código de recuperación"
                                        autoFocus={showRecoveryInput}
                                        required
                                    />
                                    <InputError
                                        message={errors['recovery_code']}
                                    />
                                </>
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

                            <div className="text-center text-sm text-muted-foreground">
                                <span>o también puedes </span>
                                <button
                                    type="button"
                                    className="cursor-pointer text-foreground underline decoration-border underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current!"
                                    onClick={() =>
                                        toggleRecoveryMode(clearErrors)
                                    }
                                >
                                    {authConfigContent.toggleText}
                                </button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}
