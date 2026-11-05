import { Head, setLayoutProps } from '@inertiajs/react';
import { HeartHandshake } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';
import ConfidentialBanner from '@/components/results/confidential-banner';
import ReferralPanel from '@/components/results/referral-panel';
import ReferralProgress from '@/components/results/referral-progress';
import ScreeningRule from '@/components/results/screening-rule';
import ScreeningsList from '@/components/results/screenings-list';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { resultsTrail } from '@/lib/results';
import type {
    DestinationOption,
    Paginated,
    ReferralCounts,
    ScreenedPerson,
    ScreeningCounts,
    ScreeningView,
    SelectedPerson,
} from '@/types';

/**
 * Board V4-N035-Canalizacion: who the Guía I says needs a clinical assessment, who waits first, and the person chosen open
 * beside them to record where they were channeled and how they were told. Never what they answered.
 */
export default function Screenings({
    campaign,
    counts,
    referralCounts,
    view,
    people,
    selected,
    destinations,
    today,
}: {
    campaign: { id: string; workCenter: string; kindLabel: string };
    counts: ScreeningCounts;
    referralCounts: ReferralCounts;
    view: ScreeningView;
    people: Paginated<ScreenedPerson>;
    selected: SelectedPerson | null;
    destinations: DestinationOption[];
    /** Y-m-d in Mexico: the day a record takes by default. */
    today: string;
}) {
    const tenantSlug = useTenantSlug();
    setLayoutProps({
        breadcrumbs: resultsTrail(tenantSlug, campaign, 'Canalización'),
    });

    return (
        <>
            <Head title={`Canalización · ${campaign.workCenter}`} />
            <div className="mx-auto flex w-full max-w-[1180px] flex-col gap-7 px-4 py-6 md:px-10 md:py-8">
                <ConfidentialBanner />
                <header className="flex flex-wrap items-end justify-between gap-6">
                    <div className="flex max-w-[760px] flex-col gap-2">
                        <p className="text-caption font-semibold tracking-[0.04em] text-primary uppercase">
                            NOM-035 · Guía I · numerales 5.5 y 5.8
                        </p>
                        <h1 className="text-3xl leading-10 font-semibold tracking-[-0.015em]">
                            Canaliza a quien lo necesite
                        </h1>
                        <p className="text-lg text-muted-foreground">
                            Estas personas de {campaign.workCenter} vivieron un
                            acontecimiento traumático severo y muestran señales
                            que conviene revisar. La norma pide que las
                            canalices a atención médica o psicológica y que
                            guardes el registro.
                        </p>
                    </div>
                    {counts.requiring > 0 && (
                        <ReferralProgress counts={referralCounts} />
                    )}
                </header>
                {counts.requiring === 0 ? (
                    <EmptyState
                        icon={HeartHandshake}
                        title="Nadie requiere valoración clínica"
                    >
                        Cuando alguien conteste la Guía I y la requiera,
                        aparecerá aquí.
                    </EmptyState>
                ) : (
                    <div className="grid items-start gap-6 min-[1240px]:grid-cols-[380px_minmax(0,1fr)]">
                        <ScreeningsList
                            campaignId={campaign.id}
                            view={view}
                            counts={referralCounts}
                            people={people}
                            selectedId={selected?.id ?? null}
                        />
                        {selected !== null && (
                            <ReferralPanel
                                key={`${selected.id}:${selected.referral?.referredOn ?? ''}`}
                                campaignId={campaign.id}
                                person={selected}
                                destinations={destinations}
                                today={today}
                            />
                        )}
                    </div>
                )}
                <ScreeningRule />
            </div>
        </>
    );
}
