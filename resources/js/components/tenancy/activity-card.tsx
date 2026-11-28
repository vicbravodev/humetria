import { Link } from '@inertiajs/react';
import Initials from '@/components/humetria/initials';
import { eventLabel } from '@/lib/audit';
import { index as audit } from '@/routes/audit';
import type { AuditEntryRow } from '@/types';

/** «Actividad reciente» (tablero Hoy): the latest entries of the audit trail, each with who did it and where. */
export default function ActivityCard({
    entries,
}: {
    entries: AuditEntryRow[];
}) {
    return (
        <section className="card">
            <div className="flex items-center justify-between gap-3 border-b px-6 py-4">
                <h2 className="text-lg font-semibold">Actividad reciente</h2>
                <Link
                    href={audit()}
                    className="inline-flex min-h-11 items-center text-sm font-medium text-primary underline-offset-4 hover:underline"
                >
                    Bitácora
                </Link>
            </div>
            {entries.length === 0 ? (
                <p className="px-6 py-5 text-sm text-muted-foreground">
                    Todavía no hay movimientos.
                </p>
            ) : (
                <ul>
                    {entries.map((entry) => (
                        <li
                            key={entry.id}
                            className="flex items-start gap-3 border-t px-6 py-3.5 first:border-t-0"
                        >
                            <Initials name={entry.actor?.name ?? 'Sistema'} />
                            <div className="min-w-0">
                                <p className="text-sm font-medium">
                                    {entry.actor?.name ?? 'Sistema'}:{' '}
                                    {eventLabel(entry.event)}
                                </p>
                                <p className="text-caption text-muted-foreground">
                                    {[entry.company?.name, entry.at]
                                        .filter(Boolean)
                                        .join(' · ')}
                                </p>
                            </div>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}
