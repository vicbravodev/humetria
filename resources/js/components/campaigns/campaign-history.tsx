import ClosedCampaignsTable from '@/components/campaigns/closed-campaigns-table';
import ConsolePagination from '@/components/console-pagination';
import type { CampaignSummary, Paginated } from '@/types';

/** "Historial" (pantalla NOM035): the closed campaigns, a page at a time; nothing before the first one closes. */
export default function CampaignHistory({
    tenantSlug,
    closed,
}: {
    tenantSlug: string;
    closed: Paginated<CampaignSummary>;
}) {
    if (closed.total === 0) {
        return null;
    }

    return (
        <section aria-labelledby="history" className="flex flex-col gap-3.5">
            <h2 id="history" className="text-lg leading-[26px] font-semibold">
                Historial
            </h2>
            <ClosedCampaignsTable
                tenantSlug={tenantSlug}
                campaigns={closed.data}
            />
            <ConsolePagination page={closed} label="Páginas del historial" />
        </section>
    );
}
