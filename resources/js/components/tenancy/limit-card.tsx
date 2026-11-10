import ProgressBar from '@/components/humetria/progress-bar';
import { Badge } from '@/components/ui/badge';
import { limitLine } from '@/lib/limits';
import type { LimitRow } from '@/types';

/** One limit of the plan: what the company has used of it, in words and in a bar, and whether it is above it. */
export default function LimitCard({ limit }: { limit: LimitRow }) {
    return (
        <div data-limit={limit.resource} className="grid gap-3 card p-5">
            <div className="flex items-center justify-between gap-3">
                <h3 className="text-base font-semibold">{limit.label}</h3>
                {limit.over && <Badge variant="outline">Por encima</Badge>}
            </div>
            <p className="text-2xl font-semibold tracking-[-0.01em]">
                {limit.used}{' '}
                <span className="text-sm font-normal text-muted-foreground">
                    de {limit.amount}
                </span>
            </p>
            <ProgressBar
                value={limit.used}
                max={limit.amount}
                label={`${limit.label} en uso`}
            />
            <p className="text-sm text-muted-foreground">{limitLine(limit)}</p>
        </div>
    );
}
