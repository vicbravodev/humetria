import type { UrlMethodPair } from '@inertiajs/core';
import { router } from '@inertiajs/react';
import { usePasskeyVerify } from '@laravel/passkeys/react';
import { KeyRound } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

type Copy = { label: string; loading: string; separator: string };

const LOGIN_COPY: Copy = {
    label: 'Entrar con llave de acceso',
    loading: 'Verificando…',
    separator: 'o con tu correo',
};

type Props = {
    routes?: {
        options: UrlMethodPair;
        submit: UrlMethodPair;
    };
    copy?: Copy;
    /** The passkey as the main action (Confirmar); on the login it is the outlined first option. */
    primary?: boolean;
};

/** The passkey button above a form, and the line that says the form is the other way in. Nothing when unsupported. */
export default function PasskeyVerify({
    routes,
    copy = LOGIN_COPY,
    primary,
}: Props = {}) {
    const { verify, isLoading, error, isSupported } = usePasskeyVerify({
        ...(routes && {
            routes: {
                options: routes.options.url,
                submit: routes.submit.url,
            },
        }),
        onSuccess: (response) => {
            router.visit(response.redirect ?? '/admin');
        },
    });

    if (!isSupported) {
        return null;
    }

    return (
        <>
            <div className="grid gap-2">
                <Button
                    type="button"
                    variant={primary === true ? 'default' : 'outline'}
                    className="w-full"
                    onClick={verify}
                    disabled={isLoading}
                >
                    {isLoading ? <Spinner /> : <KeyRound aria-hidden />}
                    {isLoading ? copy.loading : copy.label}
                </Button>
                {error && (
                    <InputError message={error} className="text-center" />
                )}
            </div>

            <div className="flex items-center gap-3 text-[13px] leading-[18px] text-muted-foreground">
                <span aria-hidden className="h-px flex-1 bg-border" />
                {copy.separator}
                <span aria-hidden className="h-px flex-1 bg-border" />
            </div>
        </>
    );
}
