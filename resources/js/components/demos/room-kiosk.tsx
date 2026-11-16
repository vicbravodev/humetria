import { Form, usePage } from '@inertiajs/react';
import { MonitorSmartphone } from 'lucide-react';
import RoomShareLink from '@/components/demos/room-share-link';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { reissue } from '@/routes/demo-room/kiosk';

/**
 * The time clock's screen at Oficinas Querétaro: a new link, shown once, to open on the tablet (or the projector) that
 * will show the code. Until it is opened the screen stays waiting.
 */
export default function RoomKiosk() {
    const link = usePage().flash.demoKioskLink;

    return (
        <section
            aria-labelledby="room-kiosk"
            className="flex flex-col gap-3 card p-5"
        >
            <h2 id="room-kiosk" className="text-base font-semibold">
                Pantalla del checador
            </h2>
            {link === undefined ? (
                <>
                    <p className="text-sm text-pretty text-muted-foreground">
                        Emite un enlace y ábrelo en la tableta o el proyector:
                        mostrará el código que el público escanea para marcar.
                        Mientras no lo abras, la pantalla queda sin activar y un
                        enlace anterior deja de servir. Si la pantalla ya
                        muestra su código, no se emite otro hasta que reinicies
                        la demo.
                    </p>
                    <Form
                        {...reissue.form()}
                        options={{ preserveScroll: true }}
                    >
                        {({ processing }) => (
                            <Button
                                type="submit"
                                variant="outline"
                                disabled={processing}
                            >
                                {processing ? (
                                    <Spinner />
                                ) : (
                                    <MonitorSmartphone aria-hidden />
                                )}
                                Emitir enlace de la pantalla
                            </Button>
                        )}
                    </Form>
                </>
            ) : (
                <RoomShareLink
                    link={link}
                    alt="Código QR del enlace de la pantalla del checador"
                >
                    Ábrelo en la pantalla ahora: funciona una sola vez y no se
                    vuelve a mostrar.
                </RoomShareLink>
            )}
        </section>
    );
}
