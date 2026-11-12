import { Head, setLayoutProps } from '@inertiajs/react';
import ItemsBody from '@/components/engagement/results/items-body';
import ResultsSubheader from '@/components/engagement/results/results-subheader';
import ResultsWaiting from '@/components/engagement/results/results-waiting';
import {
    useEngagementTrail,
    useSurveyCrumb,
} from '@/hooks/use-engagement-routes';
import type { EngagementItems } from '@/types';

/** The results question by question, theme by theme (pantalla Clima · Por pregunta). */
export default function Items({
    survey,
    available,
    sections,
}: EngagementItems) {
    setLayoutProps({
        breadcrumbs: useEngagementTrail(useSurveyCrumb(survey), {
            title: 'Por pregunta',
        }),
    });

    return (
        <>
            <Head title={`Por pregunta · ${survey.name}`} />
            <div className="flex w-full flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <ResultsSubheader survey={survey} view="items" />
                {available ? (
                    <ItemsBody sections={sections} />
                ) : (
                    <ResultsWaiting surveyId={survey.id} open />
                )}
            </div>
        </>
    );
}
