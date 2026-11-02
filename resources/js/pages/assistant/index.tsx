import { Head } from '@inertiajs/react';
import { useState } from 'react';
import HumiHistory from '@/components/assistant/humi-history';
import HumiPanel from '@/components/assistant/humi-panel';
import PageHeader from '@/components/humetria/page-header';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import type { ConversationRow, Paginated } from '@/types';

export default function AssistantIndex({
    conversations,
}: {
    conversations: Paginated<ConversationRow>;
}) {
    const slug = useTenantSlug();
    const [openId, setOpenId] = useState<string | null>(null);

    return (
        <>
            <Head title="Humi" />
            <div className="flex flex-col gap-6 px-4 py-6 md:px-10 md:py-8">
                <PageHeader
                    title="Humi"
                    description="Pregúntale por el cumplimiento de tu empresa. Aquí queda tu historial."
                />
                <div className="grid gap-6 lg:grid-cols-[300px_minmax(0,1fr)]">
                    <HumiHistory
                        slug={slug}
                        conversations={conversations}
                        openId={openId}
                        onOpen={setOpenId}
                    />
                    <div className="h-[70svh] min-h-[480px] overflow-hidden rounded-xl border bg-background">
                        <HumiPanel
                            key={openId ?? 'new'}
                            slug={slug}
                            page={null}
                            fullPage
                            onClose={null}
                            openId={openId}
                        />
                    </div>
                </div>
            </div>
        </>
    );
}
