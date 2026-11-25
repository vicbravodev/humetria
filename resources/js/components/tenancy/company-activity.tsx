import { Link } from '@inertiajs/react';
import Pagination from '@/components/humetria/pagination';
import { Button } from '@/components/ui/button';
import { eventLabel } from '@/lib/audit';
import { index as audit } from '@/routes/audit';
import type { AuditEntryRow, Paginated } from '@/types';

/** What happened in the company, newest first, from the audit trail; the whole trail is one link away. */
export default function CompanyActivity({
    companyId,
    activity,
}: {
    companyId: string;
    activity: Paginated<AuditEntryRow>;
}) {
    return (
        <div className="grid gap-3">
            {activity.total === 0 ? (
                <p className="text-sm text-muted-foreground">
                    Todavía no hay nada en su bitácora.
                </p>
            ) : (
                <ul className="divide-y card">
                    {activity.data.map((entry) => (
                        <li
                            key={entry.id}
                            className="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 px-5 py-3"
                        >
                            <span className="flex flex-col">
                                <span className="font-medium">
                                    {eventLabel(entry.event)}
                                </span>
                                <span className="text-caption text-muted-foreground">
                                    {entry.actor?.name ?? 'Sistema'}
                                </span>
                            </span>
                            <span className="text-sm text-muted-foreground tabular-nums">
                                {entry.at}
                            </span>
                        </li>
                    ))}
                </ul>
            )}
            <div className="flex flex-wrap items-center gap-3">
                <Pagination
                    page={activity}
                    label="Páginas de la actividad"
                    pinned={false}
                />
                <Button asChild variant="ghost" size="sm">
                    <Link href={audit.url({ query: { company: companyId } })}>
                        Ver en la bitácora
                    </Link>
                </Button>
            </div>
        </div>
    );
}
