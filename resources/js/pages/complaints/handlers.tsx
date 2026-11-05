import { Head, setLayoutProps } from '@inertiajs/react';
import { UserPlus } from 'lucide-react';
import { useState } from 'react';
import ComplaintsHeader from '@/components/complaints/complaints-header';
import DesignateDialog from '@/components/complaints/designate-dialog';
import HandlersTable from '@/components/complaints/handlers-table';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { channelBadge } from '@/lib/complaints/channel';
import { show as channel } from '@/routes/tenant/complaints/channel';
import type { ChannelStatus, HandlerCandidate, HandlerRow } from '@/types';

type Props = {
    channelStatus: ChannelStatus;
    handlers: HandlerRow[];
    candidates: HandlerCandidate[];
};

/** «Quiénes atienden» (board V4-Quejas-Designar): who handles the complaints and for which scope, and designating one more. */
export default function Handlers({
    channelStatus,
    handlers,
    candidates,
}: Props) {
    const tenantSlug = useTenantSlug();
    const [designating, setDesignating] = useState(false);
    const badge = channelBadge(channelStatus);
    setLayoutProps({
        breadcrumbs: [
            { title: 'Quejas' },
            { title: 'Canal', href: channel(tenantSlug) },
            { title: 'Quiénes atienden' },
        ],
    });

    return (
        <>
            <Head title="Quiénes atienden las quejas" />
            <div className="mx-auto flex w-full max-w-[1240px] flex-col gap-6 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <ComplaintsHeader
                    eyebrow="NOM-035 · numeral 8.1 b"
                    title="Quiénes atienden"
                    status={
                        <Badge variant={badge.variant}>{badge.label}</Badge>
                    }
                    lead="Solo estas personas pueden recibir quejas, y ninguna recibe una queja contra su propio rol. Cada cambio queda en la bitácora y se avisa a las demás."
                >
                    <Button onClick={() => setDesignating(true)}>
                        <UserPlus aria-hidden />
                        Designar a alguien
                    </Button>
                </ComplaintsHeader>
                <HandlersTable handlers={handlers} />
            </div>
            <DesignateDialog
                open={designating}
                onOpenChange={setDesignating}
                row={null}
                candidates={candidates}
            />
        </>
    );
}
