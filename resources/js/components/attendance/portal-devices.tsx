import { Form } from '@inertiajs/react';
import { Smartphone } from 'lucide-react';
import ToneTile from '@/components/attendance/tone-tile';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { describeDate } from '@/lib/calendar-date';

export type PortalDevice = {
    label: string;
    enrolledOn: string;
    lastUsedOn: string | null;
};

/**
 * The phones the person has linked and the button to link this one. Linking sends them to the same steps RH's email would
 * (the agreement, then the key of this phone): nothing here marks, only the key of the phone does.
 */
export default function PortalDevices({
    devices,
    enrollUrl,
}: {
    devices: PortalDevice[];
    enrollUrl: string;
}) {
    return (
        <section
            aria-labelledby="portal-devices"
            className="flex flex-col gap-4 card p-5"
        >
            <div className="flex flex-col gap-1">
                <h2 id="portal-devices" className="text-lg font-semibold">
                    Mis teléfonos
                </h2>
                <p className="text-base text-muted-foreground">
                    {devices.length === 0
                        ? 'Todavía no vinculó ningún teléfono: sin uno no puede marcar. Toma un minuto y usa el desbloqueo de este teléfono, no una contraseña.'
                        : 'Solo estos teléfonos pueden marcar por usted. Si ve uno que no es suyo, avise a Recursos Humanos.'}
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
                                    Vinculado el{' '}
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
            <Form action={enrollUrl} method="post">
                {({ processing }) => (
                    <Button
                        type="submit"
                        variant={devices.length === 0 ? 'default' : 'outline'}
                        disabled={processing}
                        className="h-12 w-full text-base font-semibold"
                    >
                        {processing && <Spinner />}
                        {devices.length === 0
                            ? 'Vincular este teléfono'
                            : 'Vincular otro teléfono'}
                    </Button>
                )}
            </Form>
        </section>
    );
}
