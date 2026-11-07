import { MapPin } from 'lucide-react';

/** Why the page asks for the position, before it does (criterio 3.4). */
export default function LocationNote() {
    return (
        <p className="flex items-start gap-3 text-base text-muted-foreground">
            <MapPin aria-hidden className="mt-1 size-5 shrink-0 text-primary" />
            Al marcar le pediremos su ubicación una sola vez, para comprobar que
            está en su centro de trabajo. No se guarda en este teléfono ni se
            sigue entre marcas.
        </p>
    );
}
