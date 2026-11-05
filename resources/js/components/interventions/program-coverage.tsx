import ProgressBar from '@/components/humetria/progress-bar';
import { themeCoverage } from '@/lib/interventions';
import type { ProgramRow } from '@/types';

/**
 * How many themes have actions and how many actions there are (board V2-N035-Plan), heading the Programa: the themes with
 * the highest level go first, so that is where to start.
 */
export default function ProgramCoverage({ rows }: { rows: ProgramRow[] }) {
    const coverage = themeCoverage(rows);

    return (
        <section
            aria-labelledby="program-coverage"
            className="flex flex-wrap items-end justify-between gap-x-10 gap-y-3"
        >
            <p className="max-w-[640px] text-lg text-muted-foreground">
                Agrega acciones a cada tema. Empieza por los de arriba: son los
                que quedaron en el riesgo más alto.
            </p>
            <div className="flex w-full max-w-[280px] flex-col gap-2">
                <p id="program-coverage" className="text-sm">
                    <span className="font-semibold">{coverage.text}</span>
                    <span className="text-muted-foreground">
                        {' '}
                        · {coverage.actions}{' '}
                        {coverage.actions === 1 ? 'acción' : 'acciones'}
                    </span>
                </p>
                <ProgressBar
                    value={coverage.covered}
                    max={coverage.total}
                    label={coverage.text}
                />
            </div>
        </section>
    );
}
