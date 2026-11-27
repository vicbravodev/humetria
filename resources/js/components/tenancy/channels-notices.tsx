import { usePage } from '@inertiajs/react';
import Notice from '@/components/humetria/notice';

/**
 * What the channels page says before the form: that the demo writes by WhatsApp for real during a guided session (by mail
 * and WhatsApp at once), and that whoever works as the owner only reads (accepting the WhatsApp words is the owner's alone, so the save answers 404 to them).
 */
export default function ChannelsNotices({ isDemo }: { isDemo: boolean }) {
    const { impersonator } = usePage().props;

    return (
        <>
            {isDemo && (
                <Notice>
                    <strong>
                        En una sesión guiada, la demo manda WhatsApp de verdad.
                    </strong>{' '}
                    Sus mensajes salen por correo y WhatsApp a la vez a las
                    personas que das de alta en el centro de la sesión.
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
