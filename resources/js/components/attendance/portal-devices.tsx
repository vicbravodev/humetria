import { Link } from '@inertiajs/react';
import { Smartphone } from 'lucide-react';
import ToneTile from '@/components/humetria/tone-tile';
import { Button } from '@/components/ui/button';
import { describeDate } from '@/lib/calendar-date';

export type PortalDevice = {
    label: string;
    enrolledOn: string;
    lastUsedOn: string | null;
};

/**
 * The person's keys in use, and how to get one: the first comes only from the link RH sends (this portal never creates
 * one); with a key, another is added from their entrance. Nothing here marks, only the key does.
 */
export default function PortalDevices({
    devices,
    keyUrl,
}: {
    devices: PortalDevice[];
    keyUrl: string;
}) {
    return (
        <section
            aria-labelledby="portal-devices"
            className="flex flex-col gap-4 card p-5"
        >
            <div className="flex flex-col gap-1">
                <h2 id="portal-devices" className="text-lg font-semibold">
                    Mis llaves de acceso
                </h2>
                <p className="text-base text-muted-foreground">
                    {devices.length === 0
                        ? 'Todavía no tiene una llave de acceso: sin ella no puede marcar. Pida a Recursos Humanos su enlace para crearla; le llega a su correo y sirve una sola vez.'
                        : 'Solo estas llaves pueden marcar por usted. Si ve una que no es suya, avise a Recursos Humanos.'}
                </p>
            </div>
            {devices.length > 0 && (
                <ul className="flex flex-col gap-3">
                    {devices.map((device) => (
                        <li
                            key={`${device.label}-${device.enrolledOn}`}
                            className="flex items-center gap-4"
                        >
                            <ToneTile icon={Smartphone} />
                            <div className="flex flex-col">
                                <p className="text-lg font-semibold">
                                    {device.label}
                                </p>
                                <p className="text-base text-muted-foreground">
                                    Registrada el{' '}
                                    {describeDate(device.enrolledOn) ??
                                        device.enrolledOn}
                                    {device.lastUsedOn !== null &&
                                        ` · usado el ${describeDate(device.lastUsedOn) ?? device.lastUsedOn}`}
                                </p>
                            </div>
                        </li>
                    ))}
                </ul>
            )}
            {devices.length > 0 && (
                <Button
                    asChild
                    variant="outline"
                    className="h-12 w-full text-base font-semibold"
                >
                    <Link href={keyUrl}>
                        Agregar una llave en este teléfono
                    </Link>
                </Button>
            )}
        </section>
    );
}
