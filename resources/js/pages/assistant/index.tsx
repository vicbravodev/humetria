import { Head } from '@inertiajs/react';
import HumiPage from '@/components/assistant/humi-page';
import type { ConversationRow, Paginated } from '@/types';

export default function AssistantIndex({
    conversations,
    filters,
}: {
    conversations: Paginated<ConversationRow>;
    filters: { q: string };
}) {
    return (
        <>
            <Head title="Humi" />
            <HumiPage conversations={conversations} filters={filters} />
        </>
    );
}
