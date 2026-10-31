import { FileText } from 'lucide-react';
import NoticeFlags from '@/components/telework/notice-flags';
import NoticeActions from '@/components/telework/notice-actions';
import { Badge } from '@/components/ui/badge';
import { describeDate } from '@/lib/calendar-date';
import type { NoticeRow as Row } from '@/types';

/** One written notice: who wrote it, what it says, whether it was reviewed and what can be done next. */
export default function NoticeRow({
    tenantSlug,
    row,
}: {
    tenantSlug: string;
    row: Row;
}) {
    const reviewed = row.reviewedOn !== null;

    return (
        <li className="flex flex-col gap-3 rounded-xl border bg-card px-[22px] py-5">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="flex min-w-0 items-start gap-3.5">
                    <span className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-accent text-primary">
                        <FileText aria-hidden className="size-5" />
                    </span>
                    <div className="flex min-w-0 flex-col">
                        <p className="font-medium">{row.kind}</p>
                        <p className="text-[13px] leading-[18px] text-muted-foreground">
                            {row.person} · {row.workCenter} · escrito el{' '}
                            {describeDate(row.writtenOn) ?? row.writtenOn},
                            ocurrió el{' '}
                            {describeDate(row.occurredOn) ?? row.occurredOn}
                        </p>
                    </div>
                </div>
                <Badge variant={reviewed ? 'success' : 'warning'}>
                    {reviewed ? 'Revisado' : 'Por revisar'}
                </Badge>
            </div>
            <p className="text-[15px] leading-[22px] whitespace-pre-line">
                {row.description}
            </p>
            <NoticeFlags row={row} />
            {row.reviewNote !== null && (
                <p className="rounded-lg bg-muted px-3.5 py-3 text-[15px] leading-[22px]">
                    Revisión del{' '}
                    {describeDate(row.reviewedOn ?? '') ?? row.reviewedOn}:{' '}
                    {row.reviewNote}
                </p>
            )}
            <NoticeActions tenantSlug={tenantSlug} row={row} />
        </li>
    );
}
