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
        <div className="grid gap-5">
            <p className="max-w-[65ch] text-lg leading-7 font-medium">
                {riskVerdict(results.final)}
                {worst !== null && (
                    <span className="font-normal text-muted-foreground">
                        {' '}
                        {worst}
                    </span>
                )}
            </p>
            <LevelBar
                counts={results.final}
                levels={levels}
                label="Cuestionarios por nivel de riesgo"
                className="h-8 rounded-md"
            />
            <LevelLegend levels={levels} counts={results.final} />
        </div>
    );
}
