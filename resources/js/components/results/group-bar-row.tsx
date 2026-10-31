import LevelBar from '@/components/humetria/level-bar';
import { highRisk, percentText } from '@/lib/results';
import type { DistributionRow, RiskLevelInfo } from '@/types';

/** One group as a bar, with its name and the share of its questionnaires in Alto or Muy alto (pantallas: Eval-Activa). */
export default function GroupBarRow({
    row,
    levels,
}: {
    row: DistributionRow;
    levels: RiskLevelInfo[];
}) {
    const total = row.levels.reduce((sum, count) => sum + count, 0);

    return (
        <li className="flex flex-col gap-1.5">
            <div className="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-0.5 text-sm">
                <p className="min-w-0 font-medium">
                    {row.label}
                    {row.answered !== undefined && (
                        <span className="font-normal text-muted-foreground tabular-nums">
                            {' '}
                            · {row.answered} cuestionarios
                        </span>
                    )}
                </p>
                <p className="tabular-nums">
                    <span className="font-semibold">
                        {percentText(highRisk(row.levels), total)}
                    </span>
                    <span className="text-muted-foreground">
                        {' '}
                        en alto o muy alto
                    </span>
                </p>
            </div>
            <LevelBar
                counts={row.levels}
                levels={levels}
                label={row.label}
                className="h-2.5 rounded-full"
            />
        </li>
    );
}
