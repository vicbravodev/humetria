import { describeDate } from '@/lib/calendar-date';
import { senderLabel } from '@/lib/complaints/follow-up';
import { cn } from '@/lib/utils';
import type { FollowUp } from '@/types';

/** The messages between the person and the handlers, the newest first, and a page at a time. */
export default function FollowUpThread({
    entries,
    processing,
    onPage,
}: {
    entries: FollowUp['entries'];
    processing: boolean;
    onPage: (page: number) => void;
}) {
    if (entries.total === 0) {
        return (
            <p className="text-muted-foreground">Todavía no hay mensajes.</p>
        );
    }

    return (
        <section aria-label="Mensajes" className="flex flex-col gap-3">
            <ol className="flex flex-col gap-3">
                {entries.data.map((entry, index) => (
                    <li
                        key={`${entries.current_page}-${index}`}
                        className={cn(
                            'rounded-xl border px-4 py-3',
                            entry.from === 'reporter' ? 'bg-accent' : 'bg-card',
                        )}
                    >
                        <p className="text-caption text-muted-foreground">
                            {senderLabel(entry.from)}
                            {entry.day === null
                                ? ''
                                : ` · ${describeDate(entry.day) ?? entry.day}`}
                        </p>
                        <p className="whitespace-pre-line">{entry.text}</p>
                    </li>
                ))}
            </ol>
            {entries.last_page > 1 && (
                <nav
                    aria-label="Páginas de mensajes"
                    className="flex items-center justify-between gap-3 text-base"
                >
                    <button
                        type="button"
                        className="touch-hit rounded-md px-3 underline disabled:no-underline disabled:opacity-50"
                        disabled={processing || entries.current_page <= 1}
                        onClick={() => onPage(entries.current_page - 1)}
                    >
                        Ver más recientes
                    </button>
                    <span className="text-muted-foreground">
                        Página {entries.current_page} de {entries.last_page}
                    </span>
                    <button
                        type="button"
                        className="touch-hit rounded-md px-3 underline disabled:no-underline disabled:opacity-50"
                        disabled={
                            processing ||
                            entries.current_page >= entries.last_page
                        }
                        onClick={() => onPage(entries.current_page + 1)}
                    >
                        Ver más antiguos
                    </button>
                </nav>
            )}
        </section>
    );
}
