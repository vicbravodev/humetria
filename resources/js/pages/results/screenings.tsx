import { Head, setLayoutProps } from '@inertiajs/react';
import { HeartHandshake } from 'lucide-react';
import Pagination from '@/components/humetria/pagination';
import EmptyState from '@/components/humetria/empty-state';
import PageHeader from '@/components/humetria/page-header';
import ConfidentialBanner from '@/components/results/confidential-banner';
import ReferralSummary from '@/components/results/referral-summary';
import ScreenedPerson from '@/components/results/screened-person';
import ScreeningRule from '@/components/results/screening-rule';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { resultsTrail } from '@/lib/results';
import type {
    Paginated,
    ReferralCounts,
    ScreenedPerson as Person,
    ScreeningCounts,
} from '@/types';

export default function Screenings({
    campaign,
    counts,
    referralCounts,
    people,
}: {
    campaign: { id: string; workCenter: string; kindLabel: string };
    counts: ScreeningCounts;
    referralCounts: ReferralCounts;
    people: Paginated<Person>;
}) {
    const tenantSlug = useTenantSlug();
    setLayoutProps({
        breadcrumbs: resultsTrail(tenantSlug, campaign, 'Canalización'),
    });

    return (
        <>
            <Head title={`Canalización · ${campaign.workCenter}`} />
            <div className="flex w-full flex-col gap-8 px-4 py-6 md:px-10 md:py-8">
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
                {people.total > 0 && (
                    <ReferralSummary counts={referralCounts} />
                )}
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
                            <ScreenedPerson
                                key={person.id}
                                campaignId={campaign.id}
                                person={person}
                            />
                        ))}
                    </ol>
                )}
                <ScreeningRule />
                <Pagination page={people} label="Páginas de personas" />
            </div>
        </>
    );
}
