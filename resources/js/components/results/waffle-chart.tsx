import { waffleCells, waffleColumns } from '@/lib/report';
import { cn } from '@/lib/utils';
import type { LevelCounts, RiskLevelInfo } from '@/types';

const FILLS = [
    'bg-risk-nulo',
    'bg-risk-bajo',
    'bg-risk-medio',
    'bg-risk-alto',
    'bg-risk-muy-alto',
];
const ENTRANCE = 1200;

/** Every questionnaire as a square in the color of its final level, from Nulo; an empty square per person missing. */
export default function WaffleChart({
    final,
    missing,
    levels,
}: {
    final: LevelCounts;
    missing: number;
    levels: RiskLevelInfo[];
}) {
    const cells = waffleCells(final, missing);
    const answered = cells.length - Math.max(0, missing);
    // However many there are, they all appear within the same time.
    const step = Math.min(28, ENTRANCE / cells.length);
    const counts = levels
        .map(
            (level, index) =>
                `${final[index] ?? 0} ${level.label.toLowerCase()}`,
        )
        .join(', ');

    return (
        <div
            role="img"
            aria-label={`${answered} cuestionarios: ${counts}${missing > 0 ? `; ${missing} sin respuesta` : ''}`}
            className="grid w-full gap-1.5"
            style={{
                gridTemplateColumns: `repeat(${waffleColumns(cells.length)}, minmax(0, 32px))`,
            }}
        >
            {cells.map((level, index) => (
                <span
                    key={index}
                    title={
                        level === null
                            ? 'Sin respuesta'
                            : `Cuestionario en nivel ${levels[level]?.label.toLowerCase() ?? ''}`
                    }
                    className={cn(
                        'aspect-square rounded-md hover:outline-2 hover:outline-offset-1 hover:outline-foreground motion-safe:animate-report-pop',
                        level === null
                            ? 'border-[1.5px] border-dashed border-input'
                            : FILLS[level],
                    )}
                    style={{ animationDelay: `${400 + index * step}ms` }}
                />
            ))}
        </div>
    );
}
