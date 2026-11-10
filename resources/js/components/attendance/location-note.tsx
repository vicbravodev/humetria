import { MapPin } from 'lucide-react';

/**
 * Why the page asks for the position, before it does (criterio 3.4). The browser asks at every tap, before the signature
 * says who marks: the server keeps it only for people who work at a center and drops it for the rest.
 */
export default function LocationNote() {
    return (
        <p className="flex items-start gap-3 rounded-xl bg-accent px-4 py-3.5 text-base">
            <MapPin
                aria-hidden
                className="mt-0.5 size-5 shrink-0 text-primary"
            />
            Al marcar, el teléfono le pedirá su ubicación: si usted trabaja en
            un centro, la usamos para comprobar que está ahí; si no, se
            descarta. No se guarda en este teléfono ni se sigue entre marcas.
        </p>
    );
}
