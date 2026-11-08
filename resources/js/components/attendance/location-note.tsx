import { MapPin } from 'lucide-react';

/**
 * Why the page asks for the position, before it does (criterio 3.4). Neutral: before the signature the page does not know
 * whether the person works on site.
 */
export default function LocationNote() {
    return (
        <p className="flex items-start gap-3 text-base text-muted-foreground">
            <MapPin aria-hidden className="mt-1 size-5 shrink-0 text-primary" />
            Si usted marca en un centro de trabajo, le pediremos su ubicación en
            ese momento, para comprobar que está ahí. No se guarda en este
            teléfono ni se sigue entre marcas.
        </p>
    );
}
