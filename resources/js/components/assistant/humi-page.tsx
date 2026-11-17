import { useState } from 'react';
import HumiHistory from '@/components/assistant/humi-history';
import HumiPanel from '@/components/assistant/humi-panel';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import type { ConversationRow, Paginated } from '@/types';

/**
 * Humi on a page of its own: the history at the left and the conversation filling the rest of the window, read in a
 * column of 720 px with the box at the bottom. On a phone the history comes first and the conversation under it.
 */
export default function HumiPage({
    conversations,
    filters,
}: {
    conversations: Paginated<ConversationRow>;
    filters: { q: string };
}) {
    const slug = useTenantSlug();
    const [openId, setOpenId] = useState<string | null>(null);
    // A new conversation starts the panel again, even from one already new.
    const [fresh, setFresh] = useState(0);

    return (
        <div className="flex flex-col lg:h-[calc(100svh-5rem-2px)] lg:flex-row">
            <div className="flex flex-col border-b px-4 py-5 lg:w-[300px] lg:shrink-0 lg:overflow-y-auto lg:border-r lg:border-b-0">
                <HumiHistory
                    slug={slug}
                    conversations={conversations}
                    filters={filters}
                    openId={openId}
                    onOpen={setOpenId}
                    onNew={() => {
                        setOpenId(null);
                        setFresh((count) => count + 1);
                    }}
                />
            </div>
            <section
                aria-label="Conversación"
                className="flex h-[calc(100svh-4rem-var(--tab-bar-clearance))] min-w-0 flex-1 flex-col md:h-[calc(100svh-5rem-2px)] lg:h-auto"
            >
                <HumiPanel
                    key={`${openId ?? 'new'}-${fresh}`}
                    slug={slug}
                    page={null}
                    fullPage
                    onClose={null}
                    openId={openId}
                />
            </section>
        </div>
    );
}
