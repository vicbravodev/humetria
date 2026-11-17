import type { WeekView } from '@/lib/attendance/week';
import { statusText } from '@/lib/attendance/week-text';
import { cn } from '@/lib/utils';

const CHIPS: Record<string, string> = {
    pending: 'bg-warning text-warning-foreground',
    confirmed: 'bg-risk-bajo-bg text-risk-bajo-fg',
    disputed: 'bg-risk-alto-bg text-risk-alto-fg',
    open: 'bg-muted text-foreground',
};

/**
 * What the week says first (board «Asistencia: su semana»): whose week the signature opened, the title with where it stands
 * beside it, what to do or when it was answered, and the person's reason when they said what is not right.
 */
export default function WeekHeading({ week }: { week: WeekView }) {
    const { label, detail } = statusText(week);

    return (
        <div className="flex flex-col gap-1.5">
            <p className="text-caption font-medium tracking-wide text-primary uppercase">
                {week.name}
            </p>
            <div className="flex items-center justify-between gap-3">
                <h1 className="text-[26px] leading-[34px] font-semibold tracking-[-0.01em]">
                    Su semana
                </h1>
                <span
                    className={cn(
                        'inline-flex min-h-8 shrink-0 items-center rounded-full px-3 text-sm font-medium whitespace-nowrap',
                        CHIPS[week.status] ?? CHIPS['open'],
                    )}
                >
                    {label}
                </span>
            </div>
            <p role="status" className="text-muted-foreground">
                {detail}
            </p>
            {week.disputeReason !== null && (
                <blockquote className="mt-1 border-l-2 pl-3 text-base text-muted-foreground">
                    {week.disputeReason}
                </blockquote>
            )}
        </div>
    );
}
