import { Head, setLayoutProps } from '@inertiajs/react';
import ResultsBody from '@/components/engagement/results/results-body';
import ResultsHeader from '@/components/engagement/results/results-header';
import ResultsNav from '@/components/engagement/results/results-nav';
import ResultsWaiting from '@/components/engagement/results/results-waiting';
import {
    useEngagementTrail,
    useSurveyCrumb,
} from '@/hooks/use-engagement-routes';
import type { EngagementResults } from '@/types';

/** The results of one survey: the reading, the indices, where to act and the map by group (pantalla Clima · Resultados). */
export default function Results(props: EngagementResults) {
    const { participation, survey } = props;

    setLayoutProps({
        breadcrumbs: useEngagementTrail(useSurveyCrumb(survey), {
            title: 'Resultados',
        }),
    });

    return (
        <>
            <Head title={`Resultados · ${survey.name}`} />
            <div className="flex w-full flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <ResultsHeader results={props} />
                {props.available && (
                    <ResultsNav
                        surveyId={survey.id}
                        active="summary"
                        commitments={
                            props.commitments > 0
                                ? props.commitments
                                : undefined
                        }
                    />
                )}
                {participation === null || participation.responses < 5 ? (
                    <ResultsWaiting
                        surveyId={survey.id}
                        open={participation === null}
                    />
                ) : (
                    <ResultsBody results={props} />
                )}
            </div>
        </>
    );
}
