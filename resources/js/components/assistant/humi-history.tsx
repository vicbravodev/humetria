import { router } from '@inertiajs/react';
import { SquarePen } from 'lucide-react';
import { useState } from 'react';
import HumiHistoryList from '@/components/assistant/humi-history-list';
import Pagination from '@/components/humetria/pagination';
import SearchField from '@/components/humetria/search-field';
import { Button } from '@/components/ui/button';
import { useQueryFilters } from '@/hooks/use-query-filters';
import { deleteConversation } from '@/lib/assistant/api';
import { groupByDay, historyQuery } from '@/lib/assistant/history';
import { index as assistantPage } from '@/routes/tenant/assistant';
import type { ConversationRow, Paginated } from '@/types';

/**
 * The column of the conversations of the person: a new one, a search by title (on the server, a page at a time) and the
 * history grouped by day, to open or delete.
 */
export default function HumiHistory({
    slug,
    conversations,
    filters,
    openId,
    onOpen,
    onNew,
}: {
    slug: string;
    conversations: Paginated<ConversationRow>;
    filters: { q: string };
    openId: string | null;
    onOpen: (id: string) => void;
    onNew: () => void;
}) {
    const [now] = useState(() => new Date());
    const { text, type } = useQueryFilters(
        assistantPage.url(slug),
        filters,
        historyQuery,
    );
    const groups = groupByDay(conversations.data, now);

    async function remove(id: string) {
        if (await deleteConversation(slug, id)) {
            router.reload({ only: ['conversations'] });
        }
    }

    return (
        <nav aria-label="Historial con Humi" className="flex flex-col gap-4">
            <Button onClick={onNew} className="h-11">
                <SquarePen aria-hidden />
                Nueva conversación
            </Button>
            <SearchField
                label="Buscar conversaciones"
                value={text}
                onChange={type}
            />
            {groups.length === 0 ? (
                <p className="px-3 text-sm text-muted-foreground">
                    {filters.q === ''
                        ? 'Todavía no tienes conversaciones con Humi.'
                        : `Ninguna conversación tiene «${filters.q}».`}
                </p>
            ) : (
                <HumiHistoryList
                    groups={groups}
                    openId={openId}
                    onOpen={onOpen}
                    onRemove={(id) => void remove(id)}
                />
            )}
            <Pagination
                page={conversations}
                label="Páginas del historial"
                previous="Ver más recientes"
                next="Ver más antiguas"
                pinned={false}
            />
            <p className="mt-auto text-caption text-muted-foreground">
                Pregúntale por el cumplimiento de tu empresa. Solo tú ves tus
                conversaciones y puedes borrarlas cuando quieras.
            </p>
        </nav>
    );
}
