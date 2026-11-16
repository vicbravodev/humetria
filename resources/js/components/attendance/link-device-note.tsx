import { Link } from '@inertiajs/react';
import { KeyRound } from 'lucide-react';

/** How to mark when this device has no key yet: the first comes from RH's link; with a key, another is added from the entrance. */
export default function LinkDeviceNote({ enterUrl }: { enterUrl: string }) {
    return (
        <section
            aria-labelledby="link-device-title"
            className="flex flex-col gap-2 card p-5"
        >
            <div className="flex items-center gap-3">
                <KeyRound
                    aria-hidden
                    className="size-6 shrink-0 text-primary"
                />
                <h2
                    id="link-device-title"
                    className="text-xl leading-7 font-semibold"
                >
                    Si aún no tiene una llave de acceso
                </h2>
            </div>
            <p className="text-base">
                Para marcar necesita una llave de acceso. Pida a Recursos
                Humanos su enlace para crearla; le llega a su correo y sirve una
                sola vez. Si ya tiene una llave en otro equipo,{' '}
                <Link
                    href={enterUrl}
                    className="font-medium text-primary underline underline-offset-4"
                >
                    entre a su portal
                </Link>{' '}
                y agregue esta. Si ya tiene llave aquí, solo marque.
            </p>
        </section>
    );
}
