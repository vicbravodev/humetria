import { Head, setLayoutProps } from '@inertiajs/react';
import { FileText } from 'lucide-react';
import AckCentersCard from '@/components/disclosure/ack-centers-card';
import AckList from '@/components/disclosure/ack-list';
import DisclosureHeader from '@/components/disclosure/disclosure-header';
import DisseminationSummary from '@/components/disclosure/dissemination-summary';
import MediaCard from '@/components/disclosure/media-card';
import PeopleResultsCard from '@/components/disclosure/people-results-card';
import EmptyState from '@/components/humetria/empty-state';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { disclosureCrumbs } from '@/lib/nom035/crumbs';
import type { DisseminationProps } from '@/types';

/**
 * «Cómo pruebas que lo difundiste» (board V4-Difusion-Registro): who confirmed the policy by center with the paper signatures,
 * the physical means with their files and the results for the people; under them, the people one by one.
 */
export default function Dissemination(props: DisseminationProps) {
    const {
        version,
        progress,
        counts,
        filters,
        people,
        today,
        publishedDay,
        records,
        centers,
    } = props;
    const tenantSlug = useTenantSlug();
    setLayoutProps({
        breadcrumbs: disclosureCrumbs(tenantSlug, { title: 'Evidencia' }),
    });

    return (
        <>
            <Head title="Cómo pruebas que lo difundiste" />
            <div className="mx-auto flex w-full max-w-[1240px] flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <DisclosureHeader
                    eyebrow="NOM-035 · numerales 5.7, 7.8 y 10.2"
                    title="Cómo pruebas que lo difundiste"
                    lead="En una inspección te pedirán evidencia de que tus personas conocen la política y los resultados. Aquí se junta sola: confirmaciones, medios físicos y lo que publicaste."
                />
                <div className="grid items-start gap-6 min-[1240px]:grid-cols-2">
                    {version === null || progress === null ? (
                        <EmptyState
                            icon={FileText}
                            title="Aún no hay una política publicada"
                        >
                            Cuando publiques la política, a las personas de tus
                            centros les llegará por correo y aquí verás quién la
                            confirmó.
                        </EmptyState>
                    ) : (
                        <AckCentersCard
                            version={version}
                            progress={progress}
                            centers={centers}
                            today={today}
                        />
                    )}
                    <div className="flex flex-col gap-6">
                        <MediaCard records={records} today={today} />
                        <PeopleResultsCard centers={centers.data} />
                    </div>
                </div>
                {version !== null && counts !== null && (
                    <section
                        aria-labelledby="people"
                        className="flex flex-col gap-4"
                    >
                        <h2
                            id="people"
                            className="text-xl leading-7 font-semibold"
                        >
                            Persona por persona
                        </h2>
                        <DisseminationSummary
                            version={null}
                            counts={counts}
                            filters={filters}
                        />
                        <AckList
                            documentId={version.id}
                            version={null}
                            people={people}
                            filters={filters}
                            days={{ first: publishedDay ?? today, today }}
                        />
                    </section>
                )}
            </div>
        </>
    );
}
