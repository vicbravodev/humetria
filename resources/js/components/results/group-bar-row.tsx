import LevelBar from '@/components/humetria/level-bar';
import { highRisk, percentText } from '@/lib/results';
import type { DistributionRow, RiskLevelInfo } from '@/types';

/** One group as a bar, with its name and the share of its questionnaires in Alto or Muy alto. */
export default function GroupBarRow({
    row,
    levels,
}: {
    row: DistributionRow;
    levels: RiskLevelInfo[];
}) {
    const total = row.levels.reduce((sum, count) => sum + count, 0);

    return (
        <li className="grid gap-2 py-3 first:pt-0 last:pb-0 sm:grid-cols-[minmax(0,15rem)_1fr_7.5rem] sm:items-center sm:gap-5">
            <div className="min-w-0">
                <p className="leading-5 font-medium">{row.label}</p>
                {row.answered !== undefined && (
                    <p className="text-sm text-muted-foreground tabular-nums">
                        {row.answered} cuestionarios
                    </p>
                )}
            </div>
            <LevelBar counts={row.levels} levels={levels} label={row.label} />
            <p className="text-sm tabular-nums sm:text-right sm:text-base">
                <span className="font-medium">
                    {percentText(highRisk(row.levels), total)}
                </span>
                <span className="text-muted-foreground sm:sr-only">
                    {' '}
                    en alto o muy alto
                </span>
            </p>
        </li>
    );
}
