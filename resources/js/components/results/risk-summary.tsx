import LevelBar from '@/components/humetria/level-bar';
import LevelLegend from '@/components/humetria/level-legend';
import { riskVerdict, worstGroupText } from '@/lib/results';
import type { RiskLevelInfo, RiskResults } from '@/types';

/** The final score of a group read in one sentence, then as one bar: how many questionnaires fall in each level. */
export default function RiskSummary({
    results,
    levels,
}: {
    results: RiskResults;
    levels: RiskLevelInfo[];
}) {
    const worst = worstGroupText(results.domains, 'dominio');

    return (
        <div className="flex flex-col gap-4">
            <p className="max-w-[520px] text-[22px] leading-8 font-medium tracking-[-0.01em] text-pretty">
                {riskVerdict(results.final)}
                {worst !== null && ` ${worst}`}
            </p>
            <LevelBar
                counts={results.final}
                levels={levels}
                label="Cuestionarios por nivel de riesgo"
                className="h-3.5 rounded-full"
            />
            <LevelLegend levels={levels} counts={results.final} />
            <p className="text-[13px] leading-[18px] text-muted-foreground">
                Cada cuestionario se califica por sí solo; no se promedian.
            </p>
        </div>
    );
}
