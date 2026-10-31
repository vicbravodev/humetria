import { Head, setLayoutProps, usePage } from '@inertiajs/react';
import { HeartHandshake } from 'lucide-react';
import ConsolePagination from '@/components/console-pagination';
import EmptyState from '@/components/humetria/empty-state';
import PageHeader from '@/components/page-header';
import ConfidentialBanner from '@/components/results/confidential-banner';
import ScreenedPerson from '@/components/results/screened-person';
import ScreeningRule from '@/components/results/screening-rule';
import { resultsTrail } from '@/lib/results';
import type {
    Paginated,
    ScreenedPerson as Person,
    ScreeningCounts,
} from '@/types';

export default function Screenings({
    campaign,
    counts,
    people,
}: {
    campaign: { id: string; workCenter: string; kindLabel: string };
    counts: ScreeningCounts;
    people: Paginated<Person>;
}) {
    const tenantSlug = usePage().props.tenant?.slug ?? '';
    setLayoutProps({
        breadcrumbs: resultsTrail(tenantSlug, campaign, 'Canalización'),
    });

    return (
        <>
            <Head title={`Canalización · ${campaign.workCenter}`} />
            <div className="flex w-full max-w-[1180px] flex-col gap-8 px-4 py-6 md:px-10 md:py-8">
                <ConfidentialBanner />
                <PageHeader
                    title="Personas que requieren valoración clínica"
                    description={
                        <>
                            <strong className="font-semibold text-foreground">
                                {counts.requiring} de {counts.answered} personas
                            </strong>{' '}
                            que contestaron la Guía I en {campaign.workCenter}{' '}
                            requieren valoración clínica. Canalízalas a atención
                            (numeral 5.5) y conserva el registro (numeral 5.8).
                        </>
                    }
                />
                {people.total === 0 ? (
                    <EmptyState
                        icon={HeartHandshake}
                        title="Nadie requiere valoración clínica"
                    >
                        Cuando alguien conteste la Guía I y la requiera,
                        aparecerá aquí.
                    </EmptyState>
                ) : (
                    <ol aria-label="Personas" className="flex flex-col gap-3">
                        {people.data.map((person) => (
                            <ScreenedPerson key={person.id} person={person} />
                        ))}
                    </ol>
                )}
                <ScreeningRule />
                <ConsolePagination
                    page={people}
                    label="Páginas de personas"
                    previous="Anteriores"
                    next="Siguientes"
                />
            </div>
        </>
    );
}
