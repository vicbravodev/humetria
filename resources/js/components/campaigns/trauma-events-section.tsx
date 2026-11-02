import CampaignCard from '@/components/campaigns/campaign-card';
import Pagination from '@/components/pagination';
import type { CampaignSummary, Paginated } from '@/types';

/** The Guía I by event still taking answers, apart from the work centers' evaluations; nothing when there are none. */
export default function TraumaEventsSection({
    tenantSlug,
    events,
}: {
    tenantSlug: string;
    events: Paginated<CampaignSummary>;
}) {
    if (events.total === 0) {
        return null;
    }

    return (
        <section aria-labelledby="events" className="flex flex-col gap-3.5">
            <h2 id="events" className="text-lg leading-[26px] font-semibold">
                Guía I por acontecimiento en curso
            </h2>
            <div className="grid grid-cols-[repeat(auto-fit,minmax(min(440px,100%),1fr))] gap-5">
                {events.data.map((campaign) => (
                    <CampaignCard
                        key={campaign.id}
                        tenantSlug={tenantSlug}
                        campaign={campaign}
                    />
                ))}
            </div>
            <Pagination
                previous="Ver más recientes"
                next="Ver más antiguas"
                page={events}
                label="Páginas de Guía I por acontecimiento"
                pinned={false}
            />
        </section>
    );
}
