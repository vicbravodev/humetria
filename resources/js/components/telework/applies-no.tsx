import { Link } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { Button } from '@/components/ui/button';
import type { NavItem } from '@/types';

/**
 * «No te aplica en este centro» (board V2-Aplica): the answer is kept with its date for an inspection, and it can be given
 * again when something changes.
 */
export default function AppliesNo({
    center,
    declaredOn,
    panelHref,
    onRestart,
}: {
    center: string;
    declaredOn: string;
    panelHref: NavItem['href'];
    onRestart: () => void;
}) {
    return (
        <div className="flex flex-col gap-5">
            <span className="inline-flex size-14 items-center justify-center rounded-full bg-muted text-muted-foreground">
                <Check aria-hidden className="size-7" />
            </span>
            <h1 className="text-3xl leading-10 font-semibold tracking-[-0.015em]">
                No te aplica en este centro
            </h1>
            <p className="text-lg leading-7 text-muted-foreground">
                Con lo que contestaste, en {center} no hay teletrabajo según la
                norma. Guardamos tus respuestas con fecha, el {declaredOn}: si
                te las piden en una inspección, están en el expediente.
            </p>
            <p className="text-muted-foreground">
                Si algo cambia, por ejemplo alguien empieza a trabajar desde
                casa 3 días por semana, vuelve a contestar y armamos tu ruta.
            </p>
            <div className="flex flex-wrap gap-3">
                <Button size="lg" asChild>
                    <Link href={panelHref}>Volver al panel</Link>
                </Button>
                <Button size="lg" variant="ghost" onClick={onRestart}>
                    Cambiar respuestas
                </Button>
            </div>
        </div>
    );
}
