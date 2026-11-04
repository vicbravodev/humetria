import CaseEntry from '@/components/complaints/case-entry';
import Pagination from '@/components/humetria/pagination';
import type { CaseEntryRow, Paginated } from '@/types';

/** The thread and the history of the case, the newest first, a page at a time. */
export default function CaseThread({
    entries,
}: {
    entries: Paginated<CaseEntryRow>;
}) {
    if (entries.total === 0) {
        return (
            <p className="rounded-xl border border-dashed bg-card px-5 py-8 text-center text-muted-foreground">
                Todavía no hay nada en este caso. Acusa recibo para que la
                persona sepa que alguien lo atiende.
            </p>
        );
    }

    return (
        <section aria-label="Hilo del caso" className="flex flex-col gap-3">
            <ol className="flex flex-col gap-3">
                {entries.data.map((entry) => (
                    <CaseEntry key={entry.id} entry={entry} />
                ))}
            </ol>
            <Pagination
                page={entries}
                label="Páginas del hilo"
                previous="Ver más recientes"
                next="Ver más antiguas"
                pinned={false}
            />
        </section>
    );
}
