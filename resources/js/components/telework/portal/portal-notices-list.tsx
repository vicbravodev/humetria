import { FileText } from 'lucide-react';
import IconTile from '@/components/humetria/icon-tile';
import Pagination from '@/components/humetria/pagination';
import PortalNoticeAnswer from '@/components/telework/portal/portal-notice-answer';
import { Badge } from '@/components/ui/badge';
import { describeDate } from '@/lib/calendar-date';
import type { Paginated, PortalNotice } from '@/types';

/**
 * What the person sent: when it was written and that it was received or reviewed; for an accident, what was done about it.
 * Never a note written for RH only.
 */
export default function PortalNoticesList({
    notices,
}: {
    notices: Paginated<PortalNotice>;
}) {
    if (notices.total === 0) {
        return null;
    }

    return (
        <section aria-labelledby="portal-notices-sent" className="grid gap-3">
            <h2 id="portal-notices-sent" className="text-xl font-semibold">
                Avisos enviados
            </h2>
            <ul className="grid gap-3">
                {notices.data.map((notice) => (
                    <li key={notice.id} className="grid gap-3 card p-4">
                        <div className="flex items-start gap-3.5">
                            <IconTile icon={FileText} />
                            <div className="flex min-w-0 flex-1 flex-col gap-1">
                                <p className="text-base font-semibold">
                                    {notice.kind}
                                </p>
                                <p className="text-base text-muted-foreground">
                                    Escrito el{' '}
                                    {describeDate(notice.writtenOn) ??
                                        notice.writtenOn}
                                </p>
                            </div>
                            <Badge variant="secondary">{notice.status}</Badge>
                        </div>
                        {notice.answer !== null && (
                            <PortalNoticeAnswer answer={notice.answer} />
                        )}
                    </li>
                ))}
            </ul>
            <Pagination
                page={notices}
                label="Páginas de avisos"
                pinned={false}
            />
        </section>
    );
}
