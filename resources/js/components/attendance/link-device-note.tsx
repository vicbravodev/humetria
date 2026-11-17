import { Link } from '@inertiajs/react';
import { KeyRound } from 'lucide-react';
import PunchMore from '@/components/attendance/punch-more';

/** How to mark in a browser where nobody has marked yet, to open and read: with a key here, just mark; the first key comes from RH's link; with a key elsewhere, another is added from the entrance. */
export default function LinkDeviceNote({ enterUrl }: { enterUrl: string }) {
    return (
        <PunchMore icon={KeyRound} title="¿Aún no tiene su llave?">
            Si ya creó su llave en este dispositivo, solo marque. Si aún no
            tiene una, pida a Recursos Humanos su enlace para crearla; le llega
            a su correo y sirve una sola vez. Si la tiene en otro equipo,{' '}
            <Link
                href={enterUrl}
                className="font-medium text-primary underline underline-offset-4"
            >
                entre a su portal
            </Link>{' '}
            y agregue esta.
        </PunchMore>
    );
}
