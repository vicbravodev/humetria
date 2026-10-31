import { FileText } from 'lucide-react';
import ConsolePagination from '@/components/console-pagination';
import { Badge } from '@/components/ui/badge';
import { describeDate } from '@/lib/calendar-date';
import type { Paginated, PortalNotice } from '@/types';

/** What the person sent: when it was written and only that it was received or reviewed, never what the Comisión wrote. */
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
                    <li
                        key={notice.id}
                        className="flex items-start gap-3.5 rounded-xl border bg-card p-4"
                    >
                        <span className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-accent text-primary">
                            <FileText aria-hidden className="size-5" />
                        </span>
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
                    </li>
                ))}
            </ul>
            <ConsolePagination
                page={notices}
                label="Páginas de avisos"
                previous="Anteriores"
                next="Siguientes"
                pinned={false}
            />
        </section>
    );
}
