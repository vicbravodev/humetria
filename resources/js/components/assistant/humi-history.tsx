import { router } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useState } from 'react';
import Pagination from '@/components/humetria/pagination';
import { deleteConversation } from '@/lib/assistant/api';
import { groupByDay } from '@/lib/assistant/history';
import type { ConversationRow, Paginated } from '@/types';

/** The conversations of the person grouped by day, a page at a time: open one, or delete it. */
export default function HumiHistory({
    slug,
    conversations,
    openId,
    onOpen,
}: {
    slug: string;
    conversations: Paginated<ConversationRow>;
    openId: string | null;
    onOpen: (id: string) => void;
}) {
    const [now] = useState(() => new Date());
    const groups = groupByDay(conversations.data, now);

    async function remove(id: string) {
        if (await deleteConversation(slug, id)) {
            router.reload({ only: ['conversations'] });
        }
    }

    if (groups.length === 0) {
        return (
            <p className="rounded-lg border bg-card p-4 text-sm text-muted-foreground">
                Todavía no tienes conversaciones con Humi.
            </p>
        );
    }

    return (
        <nav aria-label="Historial con Humi" className="flex flex-col gap-4">
            {groups.map((group) => (
                <section key={group.label} className="flex flex-col gap-1">
                    <h2 className="px-2 text-caption font-medium tracking-wide text-muted-foreground uppercase">
                        {group.label}
                    </h2>
                    <ul className="flex flex-col">
                        {group.rows.map((row) => (
                            <li
                                key={row.id}
                                className="group flex items-center rounded-md hover:bg-accent"
                            >
                                <button
                                    type="button"
                                    onClick={() => onOpen(row.id)}
                                    aria-current={
                                        row.id === openId ? 'true' : undefined
                                    }
                                    className="min-h-11 min-w-0 flex-1 truncate px-2 text-left text-base focus-visible:outline-2 focus-visible:outline-ring aria-[current=true]:font-semibold"
                                >
                                    {row.title}
                                </button>
                                <button
                                    type="button"
                                    onClick={() => void remove(row.id)}
                                    aria-label={`Borrar la conversación ${row.title}`}
                                    className="flex size-11 shrink-0 items-center justify-center text-muted-foreground hover:text-destructive focus-visible:outline-2 focus-visible:outline-ring"
                                >
                                    <Trash2 aria-hidden className="size-4" />
                                </button>
                            </li>
                        ))}
                    </ul>
                </section>
            ))}
            <Pagination
                page={conversations}
                label="Páginas del historial"
                previous="Ver más recientes"
                next="Ver más antiguas"
                pinned={false}
            />
        </nav>
    );
}
