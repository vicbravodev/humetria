import { Badge } from '@/components/ui/badge';
import { shortMoment } from '@/lib/attendance/corrections';
import { dayTitle } from '@/lib/attendance/week-text';
import { markName } from '@/lib/attendance/next-step';
import type { CorrectionDetail } from '@/types';

/** What the person asks: the mark, the time and the day in large, their reason in their words, and how it was before. */
export default function CorrectionAsk({
    detail,
}: {
    detail: CorrectionDetail;
}) {
    const [date = '', time = ''] = detail.requestedAt.split(' ');

    return (
        <div className="flex flex-col gap-3.5 rounded-xl bg-muted/50 p-5">
            <div className="flex flex-wrap items-baseline gap-x-4 gap-y-1">
                <Badge variant="default">{markName(detail.type)}</Badge>
                <span className="text-3xl leading-9 font-semibold tabular-nums">
                    {time}
                </span>
                <span className="font-medium">{dayTitle(date)}</span>
            </div>
            {detail.reason !== null && (
                <p className="text-lg text-pretty">“{detail.reason}”</p>
            )}
            <p className="text-sm text-muted-foreground">
                Lo dijo {detail.person.name} ·{' '}
                {detail.previousAt === null
                    ? 'olvidó marcarla'
                    : `la marca tenía ${shortMoment(detail.previousAt)}`}
            </p>
        </div>
    );
}
