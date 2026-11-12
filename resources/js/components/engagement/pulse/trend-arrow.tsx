import { ArrowDown, ArrowUp, Equal } from 'lucide-react';
import { changeLabel } from '@/lib/engagement/trend';
import type { TrendChange } from '@/lib/engagement/trend';

const ICONS = { up: ArrowUp, down: ArrowDown, same: Equal } as const;

/** How a figure moved, in words and with an icon (never the color alone); nothing when it cannot be compared. */
export default function TrendArrow({ change }: { change: TrendChange | null }) {
    if (change === null) {
        return null;
    }

    const Icon = ICONS[change.band];

    return (
        <span className="inline-flex items-center gap-1 text-sm font-medium">
            <Icon aria-hidden className="size-4" />
            {changeLabel(change)}
        </span>
    );
}
