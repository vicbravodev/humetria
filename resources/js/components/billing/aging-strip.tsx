import { BUCKET_LABELS } from '@/lib/billing-aging';
import { cn } from '@/lib/utils';
import type { AgingBucket, ReceivablePortfolio } from '@/types';

const TONES: Record<AgingBucket, string> = {
    current: 'bg-primary',
    '1_30': 'bg-risk-medio',
    '31_60': 'bg-risk-alto',
    over_60: 'bg-risk-muy-alto',
};

/** What is owed split by how old it is, as one strip and its legend: the legend says it in words, so the strip is not read aloud. */
export default function AgingStrip({
    aging,
}: {
    aging: ReceivablePortfolio['aging'];
}) {
    return (
        <div className="grid gap-3">
            <div
                aria-hidden
                data-slot="aging-strip"
                className="flex h-3.5 gap-0.5 overflow-hidden rounded-full bg-track"
            >
                {aging
                    .filter((part) => part.share > 0)
                    .map((part) => (
                        <span
                            key={part.key}
                            className={cn('h-full', TONES[part.key])}
                            style={{ flexGrow: part.share }}
                        />
                    ))}
            </div>
            <ul
                aria-label="Saldo por antigüedad"
                className="flex flex-wrap gap-x-6 gap-y-2 text-sm"
            >
                {aging.map((part) => (
                    <li key={part.key} className="flex items-center gap-2">
                        <span
                            aria-hidden
                            className={cn(
                                'size-2.5 rounded-[3px]',
                                TONES[part.key],
                            )}
                        />
                        {BUCKET_LABELS[part.key]}
                        <strong className="font-semibold tabular-nums">
                            {part.amount}
                        </strong>
                    </li>
                ))}
            </ul>
        </div>
    );
}
