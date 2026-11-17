import { Trash2 } from 'lucide-react';
import type { DayGroup } from '@/lib/assistant/history';

/** The conversations under their day (Hoy, Esta semana, Antes): open one, or delete it. */
export default function HumiHistoryList({
    groups,
    openId,
    onOpen,
    onRemove,
}: {
    groups: readonly DayGroup[];
    openId: string | null;
    onOpen: (id: string) => void;
    onRemove: (id: string) => void;
}) {
    return (
        <div className="flex flex-col gap-3">
            {groups.map((group) => (
                <section key={group.label} className="flex flex-col gap-0.5">
                    <h2 className="px-3 py-1 text-sm font-semibold text-muted-foreground">
                        {group.label}
                    </h2>
                    <ul className="flex flex-col gap-0.5">
                        {group.rows.map((row) => (
                            <li
                                key={row.id}
                                className="flex items-center rounded-md hover:bg-accent has-[[aria-current=true]]:bg-accent"
                            >
                                <button
                                    type="button"
                                    onClick={() => onOpen(row.id)}
                                    aria-current={
                                        row.id === openId ? 'true' : undefined
                                    }
                                    className="min-h-11 min-w-0 flex-1 truncate rounded-md px-3 text-left text-base font-medium focus-visible:outline-2 focus-visible:outline-ring"
                                >
                                    {row.title}
                                </button>
                                <button
                                    type="button"
                                    onClick={() => onRemove(row.id)}
                                    aria-label={`Borrar la conversación ${row.title}`}
                                    className="flex size-11 shrink-0 items-center justify-center rounded-md text-muted-foreground hover:text-destructive focus-visible:outline-2 focus-visible:outline-ring"
                                >
                                    <Trash2 aria-hidden className="size-4" />
                                </button>
                            </li>
                        ))}
                    </ul>
                </section>
            ))}
        </div>
    );
}
