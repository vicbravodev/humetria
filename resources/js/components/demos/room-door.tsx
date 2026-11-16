import { Form, usePage } from '@inertiajs/react';
import { DoorClosed, DoorOpen } from 'lucide-react';
import RoomShareLink from '@/components/demos/room-share-link';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { close, open } from '@/routes/demo-room/door';
import type { DemoDoor } from '@/types';

/**
 * «La puerta»: a session of two hours whose QR gives each person of the audience a free demo worker of their own. Its QR
 * shows once, when it opens: only its hash is kept.
 */
export default function RoomDoor({ door }: { door: DemoDoor }) {
    const link = usePage().flash.doorLink;

    return (
        <section
            aria-labelledby="room-door"
            className="flex flex-col gap-3 card p-5"
        >
            <div className="flex flex-wrap items-center gap-2">
                <h2 id="room-door" className="text-base font-semibold">
                    La puerta
                </h2>
                {door.open ? (
                    <Badge>{`Abierta hasta las ${door.closes_at}`}</Badge>
                ) : (
                    <Badge variant="secondary">Cerrada</Badge>
                )}
            </div>
            <p className="text-sm text-pretty text-muted-foreground">
                {`Cada quien escanea el QR y entra como una persona trabajadora de la demo. Quedan ${door.free} de ${door.total} libres; ${door.claimed} ya tienen a alguien. Cada persona se entrega una sola vez: al acabarse, la puerta dice «La sala está llena» hasta que reinicies la demo.`}
            </p>
            {link !== undefined && (
                <RoomShareLink link={link} alt="Código QR de la puerta">
                    Proyéctalo ahora: este QR no se vuelve a mostrar. Si lo
                    pierdes, abre la sala otra vez.
                </RoomShareLink>
            )}
            <Form
                {...(door.open ? close.form() : open.form())}
                options={{ preserveScroll: true }}
            >
                {({ processing }) => (
                    <Button
                        type="submit"
                        variant={door.open ? 'outline' : 'default'}
                        disabled={processing}
                    >
                        {processing && <Spinner />}
                        {!processing &&
                            (door.open ? (
                                <DoorClosed aria-hidden />
                            ) : (
                                <DoorOpen aria-hidden />
                            ))}
                        {door.open ? 'Cerrar sala' : 'Abrir sala (2 h)'}
                    </Button>
                )}
            </Form>
        </section>
    );
}
