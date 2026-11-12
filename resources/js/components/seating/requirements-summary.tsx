import ArcMeter from '@/components/humetria/arc-meter';
import type { RequirementsSummary } from '@/types';

/** How many of the thirteen requirements are met, as the arch of the logo (pantalla Eval-Activa), with what is left under it. */
export default function RequirementsSummaryCard({
    summary,
}: {
    summary: RequirementsSummary;
}) {
    const total =
        summary.met + summary.warning + summary.missing + summary.pending;

    return (
        <div className="flex flex-col items-center gap-4 card p-6 text-center lg:self-start">
            <ArcMeter
                value={summary.met}
                max={total}
                label={`${summary.met} de ${total} requisitos cumplen`}
            >
                <span className="text-4xl leading-10 font-semibold tabular-nums">
                    {summary.met}
                </span>
                <span className="text-meta text-muted-foreground">
                    de {total} cumplen
                </span>
            </ArcMeter>
            <dl className="grid w-full grid-cols-3 gap-3 border-t pt-4 text-left text-sm">
                <div>
                    <dt className="text-muted-foreground">Con aviso</dt>
                    <dd className="font-semibold tabular-nums">
                        {summary.warning}
                    </dd>
                </div>
                <div>
                    <dt className="text-muted-foreground">Por resolver</dt>
                    <dd className="font-semibold tabular-nums">
                        {summary.missing}
                    </dd>
                </div>
                <div>
                    <dt className="text-muted-foreground">Sin registros</dt>
                    <dd className="font-semibold tabular-nums">
                        {summary.pending}
                    </dd>
                </div>
            </dl>
        </div>
    );
}
