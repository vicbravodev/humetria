import { Head, Link } from '@inertiajs/react';
import { FileText, QrCode } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { evidence, poster } from '@/routes/tenant/complaints';
import ChannelChecklist from '@/components/complaints/channel-checklist';
import ChannelSummary from '@/components/complaints/channel-summary';
import OverdueNotice from '@/components/complaints/overdue-notice';
import UnassignedNotice from '@/components/complaints/unassigned-notice';
import ChannelTabs from '@/components/complaints/channel-tabs';
import ChannelActions from '@/components/complaints/channel-actions';
import ChannelLinkCard from '@/components/complaints/channel-link-card';
import ChannelSettingsForm from '@/components/complaints/channel-settings-form';
import PageHeader from '@/components/humetria/page-header';
import { channelBadge, channelSummary } from '@/lib/complaints/channel';
import type {
    ChannelBounds,
    ChannelSettings,
    ChannelState,
    ChannelSummary as ChannelSummaryProps,
    ChecklistItem,
} from '@/types';

export default function Channel({
    channel,
    settings,
    bounds,
    unassigned,
    checklist,
    overdueAcknowledgements,
    summary,
}: {
    channel: ChannelState;
    settings: ChannelSettings;
    bounds: ChannelBounds;
    /** How many open complaints nobody holds. */
    unassigned: number;
    checklist: ChecklistItem[];
    /** New cases past the acknowledgement the company set itself: a count, never which. */
    overdueAcknowledgements: number;
    summary: ChannelSummaryProps;
}) {
    const tenantSlug = useTenantSlug();
    const badge = channelBadge(channel.status);

    return (
        <>
            <Head title="Canal de quejas y denuncias" />
            <div className="flex w-full max-w-[880px] flex-col gap-6 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <PageHeader
                    caption="NOM-035 · Numeral 8.1 b"
                    title="Quejas y denuncias"
                    status={
                        <Badge variant={badge.variant}>{badge.label}</Badge>
                    }
                    description={channelSummary(channel.status)}
                >
                    {channel.status !== 'inactive' && (
                        <Button variant="outline" asChild>
                            <Link href={poster.url(tenantSlug)}>
                                <QrCode aria-hidden />
                                Cartel
                            </Link>
                        </Button>
                    )}
                    <Button variant="outline" asChild>
                        <Link href={evidence.url(tenantSlug)}>
                            <FileText aria-hidden />
                            Evidencia
                        </Link>
                    </Button>
                    {channel.status !== 'inactive' && (
                        <ChannelActions status={channel.status} />
                    )}
                </PageHeader>
                <ChannelTabs active="channel" />
                <UnassignedNotice count={unassigned} />
                <OverdueNotice count={overdueAcknowledgements} />
                <ChannelChecklist items={checklist} />
                {channel.url !== null && (
                    <ChannelLinkCard
                        url={channel.url}
                        publishedOn={channel.publishedOn}
                    />
                )}
                {channel.status !== 'inactive' && (
                    <ChannelSummary summary={summary} />
                )}
                <div className="card p-6 [&>form>section:first-child]:border-t-0 [&>form>section:first-child]:pt-0">
                    <ChannelSettingsForm
                        settings={settings}
                        bounds={bounds}
                        activating={channel.status === 'inactive'}
                    />
                </div>
            </div>
        </>
    );
}

Channel.layout = {
    breadcrumbs: [{ title: 'NOM-035' }, { title: 'Quejas y denuncias' }],
};
