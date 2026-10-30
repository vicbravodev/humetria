import { Link } from '@inertiajs/react';
import PostButton from '@/components/campaigns/post-button';
import ResendFilteredDialog from '@/components/campaigns/resend-filtered-dialog';
import PeopleTable from '@/components/campaigns/people-table';
import PeopleToolbar from '@/components/campaigns/people-toolbar';
import ConsolePagination from '@/components/console-pagination';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { show } from '@/routes/tenant/campaigns';
import { resend } from '@/routes/tenant/campaigns/participants';
import type { CampaignPerson, InstrumentProgress, PeoplePage } from '@/types';

const pending = (person: CampaignPerson) =>
    person.reachable &&
    person.guides.some((guide) => guide.status === 'pending');

/**
 * The people of a campaign a page at a time, with their filters; while it runs, sending links again to those filtered
 * or to one person.
 */
export default function CampaignPeopleSection({
    tenantSlug,
    campaignId,
    active,
    people,
    instruments,
}: {
    tenantSlug: string;
    campaignId: string;
    active: boolean;
    people: PeoplePage;
    instruments: InstrumentProgress[];
}) {
    const action = (person: CampaignPerson) =>
        pending(person) ? (
            <PostButton
                form={resend.form([tenantSlug, campaignId, person.id])}
                variant="ghost"
                size="sm"
            >
                Reenviar
            </PostButton>
        ) : null;

    const failed =
        people.standings.find((standing) => standing.value === 'failed')
            ?.count ?? 0;

    return (
        <section className="flex flex-col gap-4">
            {active && failed > 0 && people.filters.status !== 'failed' && (
                <Alert>
                    <AlertDescription>
                        <p>
                            No se pudo enviar el enlace a{' '}
                            {failed === 1 ? '1 persona' : `${failed} personas`}.{' '}
                            <Link
                                href={show.url([tenantSlug, campaignId], {
                                    query: { status: 'failed' },
                                })}
                                className="font-medium text-primary underline underline-offset-4"
                            >
                                Ver y reenviar
                            </Link>
                        </p>
                    </AlertDescription>
                </Alert>
            )}
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h2 className="text-xl leading-7 font-semibold">Personas</h2>
                {active && (
                    <ResendFilteredDialog
                        tenantSlug={tenantSlug}
                        campaignId={campaignId}
                        filters={people.filters}
                        count={people.people.total}
                    />
                )}
            </div>
            <PeopleToolbar
                tenantSlug={tenantSlug}
                url={show.url([tenantSlug, campaignId])}
                filters={people.filters}
                standings={people.standings}
                instruments={instruments}
            />
            <PeopleTable
                people={people.people.data}
                {...(active ? { action } : {})}
            />
            <ConsolePagination
                page={people.people}
                label="Páginas de personas"
                previous="Anteriores"
                next="Siguientes"
            />
        </section>
    );
}
