import { ArrowDown, ArrowUp, Equal } from 'lucide-react';
import { pointsText } from '@/lib/engagement/format';
import { cn } from '@/lib/utils';
import type { TrendChange } from '@/lib/engagement/trend';

const ICONS = { up: ArrowUp, down: ArrowDown, same: Equal } as const;

/**
 * "+9 pts desde jun 2026" (or only "+9 pts" when `bare`) with its arrow, in words: never the color alone. Nothing when it
 * cannot be compared.
 */
export default function TrendSince({
    change,
    bare = false,
}: {
    change: TrendChange | null;
    bare?: boolean;
}) {
    if (change === null) {
        return null;
    }

    const Icon = ICONS[change.band];
    const what =
        change.band === 'same' ? 'Sin cambio' : pointsText(change.points);

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1 font-medium whitespace-nowrap',
                change.band === 'up' ? 'text-primary' : 'text-foreground',
            )}
        >
            <Icon aria-hidden className="size-4" />
            {bare ? what : `${what} desde ${change.from}`}
        </span>
    );
}
