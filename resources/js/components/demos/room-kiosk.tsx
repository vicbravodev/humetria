import { Form, usePage } from '@inertiajs/react';
import { MonitorSmartphone } from 'lucide-react';
import RoomShareLink from '@/components/demos/room-share-link';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { reissue } from '@/routes/demo-room/kiosk';

/**
 * The time clock's screens, one per center: a new link for each, shown once, to open on the tablets (or side by side on the
 * projector) that will show the codes. Each person marks at the screen of their own center; until a link is opened its
 * screen stays waiting.
 */
export default function RoomKiosk() {
    const links = usePage().flash.demoKioskLinks;

    return (
        <section
            aria-labelledby="room-kiosk"
            className="flex flex-col gap-3 card p-5"
        >
            <h2 id="room-kiosk" className="text-base font-semibold">
                Pantallas del checador
            </h2>
            {links === undefined ? (
                <>
                    <p className="text-sm text-pretty text-muted-foreground">
                        Emite los enlaces y abre cada uno en su tableta o, lado
                        a lado, en el proyector: cada centro tiene su pantalla y
                        cada persona marca con la de su centro. Mientras no
                        abras un enlace, su pantalla queda sin activar y un
                        enlace anterior deja de servir. Una pantalla que ya
                        muestra su código no recibe otro hasta que reinicies la
                        demo.
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
                                Emitir enlaces de las pantallas
                            </Button>
                        )}
                    </Form>
                </>
            ) : (
                <>
                    <p className="text-sm text-pretty text-muted-foreground">
                        Ábrelos en las pantallas ahora: cada uno funciona una
                        sola vez y no se vuelve a mostrar.
                    </p>
                    {links.map((link) => (
                        <div key={link.url} className="flex flex-col gap-2">
                            <h3 className="text-sm font-medium">
                                {link.center}
                            </h3>
                            <RoomShareLink
                                link={link}
                                alt={`Código QR del enlace de la pantalla del checador de ${link.center}`}
                            />
                        </div>
                    ))}
                </>
            )}
        </section>
    );
}
