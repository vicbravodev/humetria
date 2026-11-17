import StateButton from '@/components/sso/state-button';

/** Switching single sign-on on or off, and requiring it of everyone but the owner. */
export default function StateForm({
    enabled,
    enforced,
}: {
    enabled: boolean;
    enforced: boolean;
}) {
    return (
        <div className="grid max-w-[620px] gap-5">
            <p className="text-sm">
                {enabled
                    ? enforced
                        ? 'El SSO está activo y es obligatorio: solo el owner puede seguir entrando con contraseña, como salida de emergencia.'
                        : 'El SSO está activo: tu gente puede entrar con él o con su contraseña.'
                    : 'El SSO está apagado.'}
            </p>
            {enabled ? (
                <>
                    <StateButton enabled enforced={!enforced} variant="outline">
                        {enforced
                            ? 'Dejar de exigir el SSO'
                            : 'Exigir el SSO a todos'}
                    </StateButton>
                    <StateButton
                        enabled={false}
                        enforced={false}
                        variant="outline"
                    >
                        Desactivar el SSO
                    </StateButton>
                </>
            ) : (
                <StateButton enabled enforced={false}>
                    Activar el SSO
                </StateButton>
            )}
        </div>
    );
}
