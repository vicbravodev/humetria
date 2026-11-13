import { Users } from 'lucide-react';
import IconTile from '@/components/humetria/icon-tile';
import { gapCauses } from '@/lib/compensation/start';
import { countOf } from '@/lib/plural';
import type { ScenarioReport } from '@/types';

/** Who the numbers cover: "9 de 11 personas costeadas", and who is left out by company ID, so it can be fixed in the roster. */
export default function ReportCoverage({ report }: { report: ScenarioReport }) {
    const { coverage } = report;
    const left = coverage.total - coverage.costed;

    return (
        <section
            aria-labelledby="coverage-title"
            className="flex items-start gap-3.5 card p-5"
        >
            <IconTile icon={Users} />
            <div className="grid min-w-0 gap-1">
                <h2
                    id="coverage-title"
                    className="text-lg leading-6 font-semibold"
                >
                    {coverage.costed} de{' '}
                    {countOf(
                        coverage.total,
                        'persona costeada',
                        'personas costeadas',
                    )}
                </h2>
                {left === 0 ? (
                    <p className="text-muted-foreground">
                        Toda la plantilla activa está en el cálculo.
                    </p>
                ) : (
                    <>
                        <p className="text-muted-foreground">
                            {countOf(
                                left,
                                'persona queda fuera',
                                'personas quedan fuera',
                            )}
                            : {gapCauses(coverage.causes)}.
                        </p>
                        {coverage.missing.length > 0 && (
                            <p className="text-sm">
                                <span className="text-muted-foreground">
                                    ID de la empresa:{' '}
                                </span>
                                {coverage.missing.join(', ')}
                                {coverage.missing_more > 0 &&
                                    ` y ${coverage.missing_more} más`}
                                {coverage.missing_without_number > 0 &&
                                    `. ${countOf(coverage.missing_without_number, 'más no tiene', 'más no tienen')} ID de la empresa`}
                                .
                            </p>
                        )}
                    </>
                )}
            </div>
        </section>
    );
}
