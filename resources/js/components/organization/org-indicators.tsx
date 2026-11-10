import { indicatorStats } from '@/lib/org-chart/labels';
import type { OrgIndicators as Indicators } from '@/types';

/** The shape of the company in three numbers, as the cards of the panel: a signal to look at, never a rule. */
export default function OrgIndicators({
    indicators,
}: {
    indicators: Indicators;
}) {
    return (
        <section
            aria-label="Indicadores"
            className="grid grid-cols-[repeat(auto-fit,minmax(180px,1fr))] gap-3 print:hidden"
        >
            {indicatorStats(indicators).map((stat) => (
                <div key={stat.label} className="card px-5 py-[18px]">
                    <p className="text-sm leading-5 text-muted-foreground">
                        {stat.label}
                    </p>
                    <p className="text-3xl leading-[38px] font-semibold tabular-nums">
                        {stat.value}
                    </p>
                    {stat.hint !== undefined && (
                        <p className="text-caption text-muted-foreground">
                            {stat.hint}
                        </p>
                    )}
                </div>
            ))}
        </section>
    );
}
