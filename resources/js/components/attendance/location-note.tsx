import { MapPin } from 'lucide-react';
import PunchMore from '@/components/attendance/punch-more';

/**
 * Why the page asks for the position, before it does (criterio 3.4), to open and read. The browser asks at every tap, before
 * the signature says who marks: the server keeps it only for people who work at a center and drops it for the rest.
 */
export default function LocationNote() {
    return (
        <PunchMore icon={MapPin} title="Sobre su ubicación">
            Al marcar, el teléfono le pedirá su ubicación: si usted trabaja en
            un centro, la usamos para comprobar que está ahí; si no, se
            descarta. No se guarda en este teléfono ni se sigue entre marcas.
        </PunchMore>
    );
}
