import { GitBranch } from 'lucide-react';
import type { PlacementResult as Result } from '@/types';

/** The result of Figura 1 (A, B or C) in the DOF's words; a position the nature of the work exempts says so. */
export default function PlacementResult({ result }: { result: Result }) {
    return (
        <section
            aria-label="Resultado de la Figura 1"
            className="flex items-start gap-4 card p-5"
        >
            <span className="inline-flex size-10 shrink-0 items-center justify-center rounded-lg bg-accent text-lg font-semibold text-primary">
                {result.placementLetter ?? (
                    <GitBranch aria-hidden className="size-5" />
                )}
            </span>
            <div className="flex flex-col gap-0.5">
                <h2 className="text-lg leading-[26px] font-semibold">
                    {result.placementLetter === null
                        ? 'Sin asiento por la naturaleza del trabajo'
                        : `Resultado ${result.placementLetter}`}
                </h2>
                <p className="text-sm text-muted-foreground">
                    {result.placementText ??
                        'La razón quedó por escrito en el puesto.'}
                </p>
                <p className="text-caption text-muted-foreground">
                    Se recomienda documentar todas las medidas de cumplimiento.
                </p>
            </div>
        </section>
    );
}
