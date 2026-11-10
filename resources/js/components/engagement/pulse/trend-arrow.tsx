import { ArrowDown, ArrowUp, Equal } from 'lucide-react';
import { arrowLabel } from '@/lib/engagement/trend';
import type { TrendDirection } from '@/types';

const ICONS = { up: ArrowUp, down: ArrowDown, same: Equal } as const;

/** What changed since the wave before, in words and with an icon (never the color alone); nothing without an arrow. */
export default function TrendArrow({
    direction,
}: {
    direction: TrendDirection;
}) {
    if (direction === null) {
        return null;
    }

    const Icon = ICONS[direction];

    return (
        <span className="inline-flex items-center gap-1 text-sm font-medium">
            <Icon aria-hidden className="size-4" />
            {arrowLabel(direction)}
        </span>
    );
}
