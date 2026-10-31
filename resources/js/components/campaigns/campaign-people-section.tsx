import { Link } from '@inertiajs/react';
import PeopleBulkBar from '@/components/campaigns/people-bulk-bar';
import PeopleTable from '@/components/campaigns/people-table';
import PeopleToolbar from '@/components/campaigns/people-toolbar';
import PostButton from '@/components/campaigns/post-button';
import ConsolePagination from '@/components/console-pagination';
import CountTabs from '@/components/humetria/count-tabs';
import Notice from '@/components/humetria/notice';
import { peopleQuery } from '@/lib/campaigns';
import { peopleText, visibleStandings } from '@/lib/evaluation';
import { show } from '@/routes/tenant/campaigns';
import { resend } from '@/routes/tenant/campaigns/participants';
import type { CampaignPerson, InstrumentProgress, PeoplePage } from '@/types';

const pending = (person: CampaignPerson) =>
    person.reachable &&
    person.guides.some((guide) => guide.status === 'pending');

/**
 * The people of a campaign a page at a time, split by where they stand and narrowed by the filters; while it runs, a
 * reminder to everyone the list shows, or to one person.
 */
export default function CampaignPeopleSection({
    route,
    active,
    people,
    instruments,
}: {
    /** The company's slug and the campaign's id. */
    route: [string, string];
    active: boolean;
    people: PeoplePage;
    instruments: InstrumentProgress[];
}) {
    const { filters, standings } = people;
    const url = (status: string) =>
        show.url(route, { query: peopleQuery({ ...filters, status }) });
    const failed =
        standings.find((standing) => standing.value === 'failed')?.count ?? 0;
    const action = (person: CampaignPerson) =>
        pending(person) ? (
            <PostButton
                form={resend.form([...route, person.id])}
                variant="ghost"
                size="sm"
            >
                Reenviar
            </PostButton>
        ) : null;

    return (
        <section
            aria-labelledby="people-title"
            className="flex flex-col gap-3.5"
        >
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h2
                    id="people-title"
                    className="text-lg leading-7 font-semibold"
                >
                    Personas
                </h2>
                <CountTabs
                    label="Situación de las personas"
                    tabs={visibleStandings(standings, filters.status).map(
                        (standing) => ({
                            label: standing.label,
                            count: standing.count,
                            href: url(standing.value),
                            active: standing.value === filters.status,
                        }),
                    )}
                />
            </div>
            {active && failed > 0 && filters.status !== 'failed' && (
                <Notice
                    action={
                        <Link
                            href={url('failed')}
                            className="text-sm font-semibold underline underline-offset-4"
                        >
                            Ver y reenviar
                        </Link>
                    }
                >
                    <strong>
                        No se pudo enviar el enlace a {peopleText(failed)}.
                    </strong>{' '}
                    Revisa su correo y vuelve a enviarlo.
                </Notice>
            )}
            <PeopleToolbar
                tenantSlug={route[0]}
                url={show.url(route)}
                filters={filters}
                instruments={instruments}
            />
            <div className="flex flex-col [&:has([data-slot=bulk-bar])_[data-slot=table-container]]:rounded-t-none">
                {active && (
                    <PeopleBulkBar
                        route={route}
                        filters={filters}
                        count={people.people.total}
                    />
                )}
                <PeopleTable
                    people={people.people.data}
                    {...(active ? { action } : {})}
                />
            </div>
            <ConsolePagination
                page={people.people}
                label="Páginas de personas"
                previous="Anteriores"
                next="Siguientes"
            />
        </section>
    );
}
