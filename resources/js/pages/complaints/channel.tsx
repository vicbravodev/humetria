import { Head } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import ChannelTabs from '@/components/complaints/channel-tabs';
import ChannelActions from '@/components/complaints/channel-actions';
import ChannelLinkCard from '@/components/complaints/channel-link-card';
import ChannelSettingsForm from '@/components/complaints/channel-settings-form';
import PageHeader from '@/components/humetria/page-header';
import { channelBadge, channelSummary } from '@/lib/complaints/channel';
import type { ChannelBounds, ChannelSettings, ChannelState } from '@/types';

export default function Channel({
    channel,
    settings,
    bounds,
}: {
    channel: ChannelState;
    settings: ChannelSettings;
    bounds: ChannelBounds;
}) {
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
                        <ChannelActions status={channel.status} />
                    )}
                </PageHeader>
                <ChannelTabs active="channel" />
                {channel.url !== null && (
                    <ChannelLinkCard
                        url={channel.url}
                        publishedOn={channel.publishedOn}
                    />
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
