import { Lock } from 'lucide-react';
import ClimateFirstTime from '@/components/engagement/climate-first-time';
import CreateSurveyButton from '@/components/engagement/create-survey-button';
import LatestClimateCard from '@/components/engagement/latest-climate-card';
import LatestCommitmentsCard from '@/components/engagement/latest-commitments-card';
import SurveyCard from '@/components/engagement/survey-card';
import SurveyHistory from '@/components/engagement/survey-history';
import PageHeader from '@/components/humetria/page-header';
import { homeState } from '@/lib/engagement/panel';
import type { EngagementHome } from '@/types';

/**
 * The home of Clima (pantalla Clima · Inicio): the survey in progress, or the latest result, and its commitments, so the
 * whole cycle (measure → results → commitments) reads in one screen; then the history.
 */
export default function ClimateHome({
    current,
    surveys,
    canManage,
    latest,
}: EngagementHome) {
    const state = homeState({
        current: current !== null,
        closed: surveys.total,
        scheduled: false,
    });

    return (
        <>
            <PageHeader
                title="Clima"
                facts={[
                    {
                        icon: Lock,
                        text: 'Respuestas anónimas · resultados por grupos de 5 o más',
                    },
                ]}
            >
                {canManage && state === 'history' && (
                    <CreateSurveyButton label="Nueva encuesta" />
                )}
            </PageHeader>
            {state === 'first-time' && (
                <ClimateFirstTime canManage={canManage} />
            )}
            {current !== null && (
                <SurveyCard survey={current} canManage={canManage} />
            )}
            {latest !== null && (
                <div className="grid gap-4 md:grid-cols-2">
                    <LatestClimateCard
                        latest={latest}
                        prominent={current === null}
                    />
                    <LatestCommitmentsCard latest={latest} />
                </div>
            )}
            <SurveyHistory surveys={surveys} />
        </>
    );
}
