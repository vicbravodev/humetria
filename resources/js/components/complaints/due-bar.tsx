import { cn } from '@/lib/utils';

/**
 * How much of a deadline the company set went by (boards V4-Quejas-Bandeja and V4-Quejas-Caso): terracotta when it is due
 * today or tomorrow or already passed. The text beside it says the same, so the bar is not read aloud.
 */
export default function DueBar({
    share,
    urgent,
    className,
}: {
    share: number;
    urgent: boolean;
    className?: string;
}) {
    return (
        <div
            aria-hidden
            className={cn(
                'h-1.5 overflow-hidden rounded-full bg-track',
                className,
            )}
        >
            <div
                className={cn(
                    'h-full rounded-full',
                    urgent ? 'bg-risk-alto' : 'bg-primary',
                )}
                style={{ width: `${Math.round(share * 100)}%` }}
            />
        </div>
    );
}
