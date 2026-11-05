import { Head, Link, setLayoutProps, usePage } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import ChannelActivate from '@/components/complaints/channel-activate';
import ChannelOpen from '@/components/complaints/channel-open';
import ComplaintsHeader from '@/components/complaints/complaints-header';
import OverdueNotice from '@/components/complaints/overdue-notice';
import UnassignedNotice from '@/components/complaints/unassigned-notice';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { channelBadge, channelSince } from '@/lib/complaints/channel';
import { index } from '@/routes/tenant/complaints';
import type {
    ChannelBounds,
    ChannelSettings,
    ChannelState,
    ChannelSummary,
    CoverageByKind,
    HandlerCandidate,
} from '@/types';

type Props = {
    channel: ChannelState & { qr: string | null };
    settings: ChannelSettings;
    bounds: ChannelBounds;
    /** How many open complaints nobody holds. */
    unassigned: number;
    /** New cases past the acknowledgement the company set itself: a count, never which. */
    overdueAcknowledgements: number;
    summary: ChannelSummary;
    coverage: CoverageByKind[];
    handlersCount: number;
    candidates: HandlerCandidate[];
};

/** «El canal y a quién llega cada queja» (board V4-Quejas-Canal). */
export default function Channel({
    channel,
    unassigned,
    overdueAcknowledgements,
    ...rest
}: Props) {
    const tenantSlug = useTenantSlug();
    const handles = usePage().props.tenant?.can.handle_complaints ?? false;
    const { url, qr } = channel;
    setLayoutProps({
        breadcrumbs: handles
            ? [{ title: 'Quejas', href: index(tenantSlug) }, { title: 'Canal' }]
            : [{ title: 'Quejas' }, { title: 'Canal' }],
    });

    return (
        <>
            <Head title="Canal de quejas y denuncias" />
            <div className="mx-auto flex w-full max-w-[1240px] flex-col gap-6 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <ComplaintsHeader
                    eyebrow="NOM-035 · numeral 8.1 b"
                    title="Canal de quejas y denuncias"
                    status={
                        <Badge variant={channelBadge(channel.status).variant}>
                            {channelSince(channel.status, channel.publishedOn)}
                        </Badge>
                    }
                    lead="Por aquí tus personas avisan, de forma anónima o confidencial, de un mal ambiente de trabajo, de violencia laboral o de un acontecimiento traumático severo."
                >
                    {handles && (
                        <Button variant="outline" asChild>
                            <Link href={index(tenantSlug)}>
                                Ver los casos
                                <ArrowRight aria-hidden />
                            </Link>
                        </Button>
                    )}
                </ComplaintsHeader>
                <UnassignedNotice count={unassigned} />
                <OverdueNotice count={overdueAcknowledgements} />
                {url === null || qr === null ? (
                    <ChannelActivate
                        settings={rest.settings}
                        bounds={rest.bounds}
                    />
                ) : (
                    <ChannelOpen channel={{ ...channel, url, qr }} {...rest} />
                )}
            </div>
        </>
    );
}
