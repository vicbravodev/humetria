import { ArrowDown, ArrowUp, Minus } from 'lucide-react';
import { deltaText } from '@/lib/engagement/format';
import type { Delta } from '@/lib/engagement/format';

/**
 * What a result did since the previous survey: an arrow only when both surveys had enough answers for one, always with
 * the words, so the direction never rests on the arrow or its color alone.
 */
export default function TrendBadge({ delta }: { delta: Delta | null }) {
    if (delta === null) {
        return null;
    }

    const Icon =
        delta.direction === 'up'
            ? ArrowUp
            : delta.direction === 'down'
              ? ArrowDown
              : delta.direction === 'same'
                ? Minus
                : null;

    return (
        <span
            data-slot="trend-badge"
            className="inline-flex items-center gap-1 text-caption whitespace-nowrap text-muted-foreground"
        >
            {Icon !== null && <Icon aria-hidden className="size-3.5" />}
            {deltaText(delta)}
        </span>
    );
}
