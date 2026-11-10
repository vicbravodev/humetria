import { Head, setLayoutProps } from '@inertiajs/react';
import { Gauge } from 'lucide-react';
import CreateSurveyButton from '@/components/engagement/create-survey-button';
import SurveyCard from '@/components/engagement/survey-card';
import SurveyHistory from '@/components/engagement/survey-history';
import EmptyState from '@/components/humetria/empty-state';
import PageHeader from '@/components/humetria/page-header';
import type {
    ClimateSurveyCard,
    ClimateSurveyHistoryRow,
    Paginated,
} from '@/types';

/**
 * The home of Clima (pantalla NOM035): the survey in progress, or the next step when there is none, and the history of
 * the closed ones. Only counts: RH never sees who answered.
 */
export default function EngagementIndex({
    current,
    surveys,
    canManage,
}: {
    current: ClimateSurveyCard | null;
    surveys: Paginated<ClimateSurveyHistoryRow>;
    canManage: boolean;
}) {
    setLayoutProps({ breadcrumbs: [{ title: 'Clima' }] });

    return (
        <>
            <Head title="Clima" />
            <div className="flex w-full flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <PageHeader
                    title="Clima"
                    description="Cómo se vive trabajar en tu empresa. Las respuestas son anónimas y los resultados solo se muestran por grupos."
                />
                {current !== null ? (
                    <SurveyCard survey={current} />
                ) : (
                    <EmptyState
                        icon={Gauge}
                        title="Todavía no hay una encuesta de clima"
                        {...(canManage
                            ? {
                                  action: (
                                      <CreateSurveyButton
                                          label={
                                              surveys.total === 0
                                                  ? 'Crear la primera encuesta de clima'
                                                  : 'Crear una encuesta de clima'
                                          }
                                      />
                                  ),
                              }
                            : {})}
                    >
                        Prepara el borrador, revisa a quién se invita y lánzala
                        cuando estés listo. Nadie recibe nada hasta entonces.
                    </EmptyState>
                )}
                <SurveyHistory surveys={surveys} />
            </div>
        </>
    );
}
