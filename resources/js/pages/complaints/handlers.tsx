import { Head } from '@inertiajs/react';
import ChannelTabs from '@/components/complaints/channel-tabs';
import CoverageList from '@/components/complaints/coverage-list';
import DesignateForm from '@/components/complaints/designate-form';
import HandlersTable from '@/components/complaints/handlers-table';
import PageHeader from '@/components/humetria/page-header';
import { channelBadge } from '@/lib/complaints/channel';
import { Badge } from '@/components/ui/badge';
import type { Option } from '@/lib/option-search';
import type { ChannelStatus, CoverageRow, HandlerRow } from '@/types';

export default function Handlers({
    channelStatus,
    handlers,
    candidates,
    coverage,
}: {
    channelStatus: ChannelStatus;
    handlers: HandlerRow[];
    candidates: Option[];
    coverage: CoverageRow[];
}) {
    const badge = channelBadge(channelStatus);

    return (
        <>
            <Head title="Quién atiende las quejas" />
            <div className="flex w-full max-w-[880px] flex-col gap-6 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <PageHeader
                    caption="NOM-035 · Numeral 8.1 b"
                    title="Quién atiende"
                    status={
                        <Badge variant={badge.variant}>{badge.label}</Badge>
                    }
                    description="Solo estas personas pueden recibir quejas, y ninguna recibe una queja contra su propio rol. Cada cambio queda en la bitácora y se avisa a las demás."
                />
                <ChannelTabs active="handlers" />
                <HandlersTable handlers={handlers} />
                <section className="flex flex-col gap-3">
                    <h2 className="text-lg font-semibold">
                        A quién llegaría cada queja
                    </h2>
                    <p className="text-sm text-muted-foreground">
                        Si no queda nadie independiente para una opción, el
                        formulario no la acepta: la queja llegaría a la persona
                        señalada. Designa a alguien, incluso de fuera de la
                        empresa, para cubrirla.
                    </p>
                    <CoverageList rows={coverage} />
                </section>
                <section className="flex flex-col gap-3">
                    <h2 className="text-lg font-semibold">
                        Designar a alguien
                    </h2>
                    <DesignateForm candidates={candidates} />
                </section>
            </div>
        </>
    );
}

Handlers.layout = {
    breadcrumbs: [{ title: 'NOM-035' }, { title: 'Quién atiende las quejas' }],
};
