import CountTabs from '@/components/humetria/count-tabs';
import Pagination from '@/components/humetria/pagination';
import ScreenedRow from '@/components/results/screened-row';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { screenings } from '@/routes/tenant/campaigns';
import type {
    Paginated,
    ReferralCounts,
    ScreenedPerson,
    ScreeningView,
} from '@/types';

/**
 * Who waits to be channeled, and who was a click away (board V4-N035-Canalizacion): a page at a time, each row opening the
 * person beside the list.
 */
export default function ScreeningsList({
    campaignId,
    view,
    counts,
    people,
    selectedId,
}: {
    campaignId: string;
    view: ScreeningView;
    counts: ReferralCounts;
    people: Paginated<ScreenedPerson>;
    selectedId: string | null;
}) {
    const tenantSlug = useTenantSlug();
    const href = (query: Record<string, string>) =>
        screenings.url([tenantSlug, campaignId], { query });

    return (
        <section
            aria-labelledby="screenings-list"
            className="flex flex-col self-start overflow-hidden card"
        >
            <div className="flex flex-col gap-3 px-[18px] py-4">
                <h2 id="screenings-list" className="font-semibold">
                    {view === 'pending' ? 'Por canalizar' : 'Canalizadas'}
                </h2>
                <CountTabs
                    label="Canalización de las personas"
                    tabs={[
                        {
                            label: 'Por canalizar',
                            count: counts.pending,
                            href: href({ view: 'pending' }),
                            active: view === 'pending',
                        },
                        {
                            label: 'Canalizadas',
                            count: counts.referred + counts.attended,
                            href: href({ view: 'channeled' }),
                            active: view === 'channeled',
                        },
                    ]}
                />
            </div>
            {people.data.length === 0 ? (
                <p className="border-t px-[18px] py-6 text-sm text-muted-foreground">
                    {view === 'pending'
                        ? 'Nadie espera canalización. Las personas que ya canalizaste están en Canalizadas.'
                        : 'Todavía no canalizas a nadie.'}
                </p>
            ) : (
                <ul aria-label="Personas">
                    {people.data.map((person) => (
                        <ScreenedRow
                            key={person.id}
                            person={person}
                            href={href({ view, person: person.id })}
                            open={person.id === selectedId}
                        />
                    ))}
                </ul>
            )}
            <div className="border-t px-[18px] py-2 empty:hidden">
                <Pagination
                    page={people}
                    label="Páginas de personas"
                    pinned={false}
                />
            </div>
        </section>
    );
}
