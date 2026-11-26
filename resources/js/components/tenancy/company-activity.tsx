import { Link } from '@inertiajs/react';
import Pagination from '@/components/humetria/pagination';
import { Button } from '@/components/ui/button';
import { eventLabel } from '@/lib/audit';
import { initials } from '@/lib/initials';
import { index as audit } from '@/routes/audit';
import type { AuditEntryRow, Paginated } from '@/types';

const SUMMARY_ROWS = 5;

/**
 * What happened in the company, newest first, from the audit trail (tablero Empresa 360): who, what and when. The summary
 * shows the latest five; its tab, a page at a time. The whole trail is one link away.
 */
export default function CompanyActivity({
    companyId,
    activity,
    summary = false,
}: {
    companyId: string;
    activity: Paginated<AuditEntryRow>;
    summary?: boolean;
}) {
    const rows = summary ? activity.data.slice(0, SUMMARY_ROWS) : activity.data;

    return (
        <section aria-labelledby="company-activity" className="card">
            <div className="flex items-center gap-3 border-b px-6 py-4">
                <h2
                    id="company-activity"
                    className="flex-1 text-base font-semibold"
                >
                    Actividad
                </h2>
                <Button asChild variant="ghost" size="sm">
                    <Link href={audit.url({ query: { company: companyId } })}>
                        Ver en bitácora
                    </Link>
                </Button>
            </div>
            {rows.length === 0 ? (
                <p className="px-6 py-4 text-sm text-muted-foreground">
                    Todavía no hay nada en su bitácora.
                </p>
            ) : (
                <ul className="divide-y">
                    {rows.map((entry) => (
                        <li key={entry.id} className="flex gap-3.5 px-6 py-3">
                            <span
                                aria-hidden
                                className="inline-flex size-8 shrink-0 items-center justify-center rounded-full bg-secondary text-caption font-semibold"
                            >
                                {initials(entry.actor?.name ?? 'Sistema')}
                            </span>
                            <span className="grid min-w-0 flex-1">
                                <span className="font-medium">
                                    {eventLabel(entry.event)}
                                </span>
                                <span className="text-caption text-muted-foreground">
                                    {entry.actor?.name ?? 'Sistema'}
                                </span>
                            </span>
                            <span className="shrink-0 text-caption text-muted-foreground tabular-nums">
                                {entry.at}
                            </span>
                        </li>
                    ))}
                </ul>
            )}
            {!summary && (
                <Pagination
                    page={activity}
                    label="Páginas de la actividad"
                    pinned={false}
                />
            )}
        </section>
    );
}
