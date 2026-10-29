import { Armchair, BrainCircuit, House } from 'lucide-react';
import type { PreviewTool } from '@/components/landing/hero-preview-tool';
import HeroPreviewTool from '@/components/landing/hero-preview-tool';

const tools: readonly PreviewTool[] = [
    {
        key: 'nom_035',
        icon: BrainCircuit,
        name: 'NOM-035',
        status: 'En curso',
        variant: 'default',
        detail: 'Guía III · 68 de 92',
        progress: 'w-[74%]',
    },
    {
        key: 'nom_037',
        icon: House,
        name: 'NOM-037',
        status: 'Por revisar',
        variant: 'outline',
        detail: '2 verificaciones por validar',
    },
    {
        key: 'ley_silla',
        icon: Armchair,
        name: 'Ley Silla',
        status: 'En curso',
        variant: 'secondary',
        detail: 'Plan de acción · 3 de 5',
        progress: 'w-3/5',
    },
];

/** A still picture of a work center's board: where each tool stands, never an individual answer. */
export default function HeroPreview() {
    return (
        <figure
            data-test="hero-preview"
            className="rounded-xl border bg-card p-6 shadow-raised sm:p-8"
        >
            <figcaption className="sr-only">
                Ejemplo del tablero de un centro de trabajo en Humetría
            </figcaption>
            <div>
                <p className="font-medium">Planta Querétaro</p>
                <p className="text-sm text-muted-foreground">
                    120 trabajadores
                </p>
            </div>

            <ul className="mt-4 divide-y border-y">
                {tools.map((tool) => (
                    <HeroPreviewTool key={tool.key} tool={tool} />
                ))}
            </ul>
        </figure>
    );
}
