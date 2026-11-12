import ProtectedCell from '@/components/humetria/protected-cell';
import { TableCell } from '@/components/ui/table';
import { tierOf } from '@/lib/engagement/results';
import { cn } from '@/lib/utils';

const TIERS = {
    low: 'bg-muted text-foreground',
    mid: 'bg-accent text-foreground',
    high: 'bg-primary text-primary-foreground',
} as const;

/**
 * A cell of the map by group: the favorable share on a fill of one hue in three tiers, always with its figure. A hidden
 * one is «Protegido», the same whatever the reason.
 */
export default function GroupMapCell({
    score,
    label,
}: {
    score: number | null;
    /** What the cell is, for screen readers: "Planta · Reconocimiento". */
    label: string;
}) {
    const tier = tierOf(score);

    return (
        <TableCell className="p-1">
            {tier === null ? (
                <span className="flex h-11 items-center justify-center rounded-md border border-dashed">
                    <ProtectedCell />
                </span>
            ) : (
                <span
                    aria-label={`${label}: ${score} %`}
                    className={cn(
                        'flex h-11 items-center justify-center rounded-md font-medium tabular-nums',
                        TIERS[tier],
                    )}
                >
                    {score} %
                </span>
            )}
        </TableCell>
    );
}
