import type { PreviewState } from '@/components/landing/hero-preview-tool';
import HeroPreviewTool from '@/components/landing/hero-preview-tool';
import type { ToolKey } from '@/components/landing/landing-tools';
import { TOOLS } from '@/components/landing/landing-tools';

const states: Readonly<Record<ToolKey, PreviewState>> = {
    nom_035: {
        status: 'En curso',
        detail: 'Guía III · 68 de 92',
        progress: 'w-[74%]',
    },
    nom_037: { status: 'Por revisar', detail: '2 verificaciones por validar' },
    ley_silla: {
        status: 'En curso',
        detail: 'Plan de acción · 3 de 5',
        progress: 'w-3/5',
    },
};

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
                {TOOLS.map((tool) => (
                    <HeroPreviewTool
                        key={tool.key}
                        tool={tool}
                        state={states[tool.key]}
                    />
                ))}
            </ul>
        </figure>
    );
}
