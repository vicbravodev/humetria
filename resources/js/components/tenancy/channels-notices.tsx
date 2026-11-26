import { usePage } from '@inertiajs/react';
import Notice from '@/components/humetria/notice';

/**
 * What the channels page says before the form: that the demo never writes by WhatsApp, and that whoever works as the owner
 * only reads (accepting the WhatsApp words is the owner's alone, so the save answers 404 to them).
 */
export default function ChannelsNotices({ isDemo }: { isDemo: boolean }) {
    const { impersonator } = usePage().props;

    return (
        <>
            {isDemo && (
                <Notice>
                    <strong>La empresa demo nunca manda WhatsApp.</strong>{' '}
                    Aunque lo actives aquí, sus invitaciones salen solo por
                    correo, para no escribirle a números reales.
                </Notice>
            )}
            {impersonator !== null && (
                <Notice>
                    <strong>
                        Solo el dueño de la empresa puede cambiar los canales.
                    </strong>{' '}
                    Activar WhatsApp acepta un aviso en su nombre: entra con su
                    cuenta para hacerlo.
                </Notice>
            )}
        </>
    );
}
