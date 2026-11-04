import CountTabs from '@/components/humetria/count-tabs';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { show } from '@/routes/tenant/complaints/channel';
import { index } from '@/routes/tenant/complaints/handlers';

/** The two pages of the channel's administration: how it works, and who handles it. */
export default function ChannelTabs({
    active,
}: {
    active: 'channel' | 'handlers';
}) {
    const tenantSlug = useTenantSlug();

    return (
        <CountTabs
            label="Administración del canal"
            tabs={[
                {
                    label: 'Canal',
                    href: show(tenantSlug),
                    active: active === 'channel',
                },
                {
                    label: 'Quién atiende',
                    href: index(tenantSlug),
                    active: active === 'handlers',
                },
            ]}
        />
    );
}
