import { TableCell, TableRow } from '@/components/ui/table';
import { highTone } from '@/lib/report';
import { highRisk, percentText, shareOf } from '@/lib/results';
import { cn } from '@/lib/utils';
import type { LevelCounts } from '@/types';

const TONES = {
    very_high: 'text-risk-muy-alto-fg',
    high: 'text-risk-alto-fg',
};

/** One group of Anexo A: how many questionnaires fall in each level, the share of each, and the share from Alto up. */
export default function AnnexRow({
    label,
    counts,
    kind,
}: {
    label: string;
    counts: LevelCounts;
    kind: 'final' | 'category' | 'domain';
}) {
    const answered = counts.reduce((sum, count) => sum + count, 0);
    const high = Math.round(shareOf(highRisk(counts), answered));
    const tone = highTone(high);

    return (
        <TableRow
            className={cn(
                'hover:bg-accent',
                kind === 'final' && 'border-b-2 text-base',
                kind === 'category' && 'bg-muted',
            )}
        >
            <TableCell
                className={cn(
                    'whitespace-normal',
                    kind === 'domain' ? 'pl-9' : 'pl-5 font-semibold',
                    kind === 'final' && 'py-3.5',
                )}
            >
                {label}
            </TableCell>
            {counts.map((count, index) => (
                <TableCell key={index} className="text-right tabular-nums">
                    <span
                        className={cn(
                            'block',
                            kind === 'domain' ? 'font-medium' : 'font-semibold',
                            count === 0 && 'text-muted-foreground',
                        )}
                    >
                        {count}
                    </span>
                    <span className="block text-xs text-muted-foreground">
                        {percentText(count, answered)}
                    </span>
                </TableCell>
            ))}
            <TableCell
                className={cn(
                    'pr-5 text-right font-semibold tabular-nums',
                    tone !== null && TONES[tone],
                )}
            >
                {high} %
            </TableCell>
        </TableRow>
    );
}
