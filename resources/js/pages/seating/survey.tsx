import { Head, setLayoutProps } from '@inertiajs/react';
import { Factory } from 'lucide-react';
import PageHeader from '@/components/humetria/page-header';
import SurveyLaunchForm from '@/components/seating/survey-launch-form';
import SurveyProgress from '@/components/seating/survey-progress';
import SurveyResults from '@/components/seating/survey-results';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { survey as surveyRoute } from '@/routes/tenant/seating';
import { seatingCrumbs } from '@/lib/seating/crumbs';
import type { SurveyRecord, SurveyResults as Results } from '@/types';

export default function SeatingSurvey({
    workCenter,
    survey,
    results,
    canLaunch,
    minGroup,
}: {
    workCenter: { id: string; name: string };
    survey: SurveyRecord | null;
    results: Results | null;
    canLaunch: boolean;
    minGroup: number;
}) {
    const tenantSlug = useTenantSlug();
    setLayoutProps({
        breadcrumbs: seatingCrumbs(tenantSlug, workCenter, {
            title: 'Encuesta',
        }),
    });

    return (
        <>
            <Head title={`Encuesta · ${workCenter.name}`} />
            <div className="flex w-full flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <PageHeader
                    title="Encuesta confidencial"
                    description="Pregunta a todo el centro por sus asientos y sus descansos. Las respuestas son anónimas y los resultados solo se muestran con grupos de suficientes personas."
                    facts={[{ icon: Factory, text: workCenter.name }]}
                />
                {survey !== null && survey.status === 'active' && (
                    <SurveyProgress
                        survey={survey}
                        workCenterId={workCenter.id}
                    />
                )}
                {results !== null && (
                    <SurveyResults
                        url={surveyRoute.url([tenantSlug, workCenter.id])}
                        results={results}
                        minGroup={minGroup}
                    />
                )}
                {canLaunch && (
                    <div className="max-w-[880px] card p-6">
                        <SurveyLaunchForm workCenterId={workCenter.id} />
                    </div>
                )}
            </div>
        </>
    );
}
