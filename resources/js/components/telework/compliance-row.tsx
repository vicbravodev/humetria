import { Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { rowTone } from '@/lib/telework/center';
import type { ComplianceRow as Row } from '@/types';

/** One row of table 10.3: the numeral, what it asks, where it stands and why, and the way to fix it. */
export default function ComplianceRow({ row }: { row: Row }) {
    return (
        <li className="grid gap-x-4 gap-y-1 border-t px-[22px] py-4 first:border-t-0 sm:grid-cols-[88px_1fr_auto] sm:items-start">
            <span className="text-sm font-medium text-muted-foreground tabular-nums">
                {row.numeral}
            </span>
            <div className="flex min-w-0 flex-col gap-0.5">
                <p className="font-medium">{row.label}</p>
                {row.detail !== null && (
                    <p className="text-[15px] leading-[22px] text-muted-foreground">
                        {row.detail}
                    </p>
                )}
                {row.href !== null && row.action !== null && (
                    <Link
                        href={row.href}
                        className="touch-hit mt-1 w-fit rounded-sm text-[15px] font-medium text-primary underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                    >
                        {row.action}
                        <span className="sr-only">
                            {' '}
                            del numeral {row.numeral}
                        </span>
                    </Link>
                )}
            </div>
            <Badge variant={rowTone(row.state)}>{row.stateLabel}</Badge>
        </li>
    );
}
