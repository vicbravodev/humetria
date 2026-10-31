import { FileText } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { describeDate } from '@/lib/calendar-date';
import type { TeleworkerSummary } from '@/types';

/** The written notices of the person (numerals 6.3, 6.7, 6.9) with where each review stands. */
export default function NoticesSection({
    teleworker,
}: {
    teleworker: TeleworkerSummary;
}) {
    return (
        <section className="grid gap-3" aria-labelledby="notices-title">
            <h2 id="notices-title" className="text-xl font-semibold">
                Avisos de la persona
            </h2>
            {teleworker.notices.length === 0 ? (
                <p className="flex items-center gap-3 rounded-xl border bg-card p-4 text-[15px] text-muted-foreground">
                    <FileText aria-hidden className="size-5 shrink-0" />
                    Esta persona aún no ha enviado avisos.
                </p>
            ) : (
                <ul className="grid gap-3">
                    {teleworker.notices.map((notice) => (
                        <li
                            key={notice.id}
                            className="rounded-xl border bg-card p-4"
                        >
                            <p className="flex flex-wrap items-center gap-x-3 gap-y-1 text-base font-semibold">
                                {notice.kind}
                                <Badge
                                    variant={
                                        notice.status === 'Revisado'
                                            ? 'success'
                                            : 'warning'
                                    }
                                >
                                    {notice.status}
                                </Badge>
                            </p>
                            <p className="mt-1 text-[15px] text-muted-foreground">
                                Escrito el{' '}
                                {describeDate(notice.writtenOn) ??
                                    notice.writtenOn}
                                , ocurrió el{' '}
                                {describeDate(notice.occurredOn) ??
                                    notice.occurredOn}
                            </p>
                            <p className="mt-1 text-[15px] whitespace-pre-line">
                                {notice.description}
                            </p>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}
