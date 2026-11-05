import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import type { NavItem } from '@/types';

/**
 * The one action of the Ley Silla result (board V2-Aplica-Otras): keep the answers while they differ from what is
 * declared; once kept, the route where it applies, or back to the panel and the certificate where it does not.
 */
export default function AppliesSeatingAction({
    applies,
    changed,
    processing,
    declaredOn,
    onSave,
    routeHref,
    panelHref,
    certificateHref,
}: {
    applies: boolean | null;
    changed: boolean;
    processing: boolean;
    declaredOn: string | null;
    onSave: () => void;
    routeHref: NavItem['href'];
    panelHref: NavItem['href'];
    certificateHref: NavItem['href'];
}) {
    if (applies === null) {
        return null;
    }

    if (changed) {
        return (
            <Button size="lg" onClick={onSave} disabled={processing}>
                Guardar declaración
            </Button>
        );
    }

    return (
        <>
            <Button size="lg" asChild>
                <Link href={applies ? routeHref : panelHref}>
                    {applies ? 'Ver mi ruta' : 'Volver al panel'}
                </Link>
            </Button>
            {!applies && (
                <Button size="lg" variant="outline" asChild>
                    <Link href={certificateHref}>Ver constancia</Link>
                </Button>
            )}
            {declaredOn !== null && (
                <span className="text-sm text-muted-foreground">
                    Declarado el {declaredOn}
                </span>
            )}
        </>
    );
}
