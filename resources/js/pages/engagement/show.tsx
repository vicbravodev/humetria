import { Head, setLayoutProps } from '@inertiajs/react';
import SurveyBody from '@/components/engagement/survey-body';
import SurveyHeader from '@/components/engagement/survey-header';
import { useEngagementTrail } from '@/hooks/use-engagement-routes';
import type {
    ClimateDraft,
    ClimateParticipation,
    ClimateSection,
    ClimateSurveyCard,
    ClimateUnreachable,
    OpenCommitments,
    Paginated,
} from '@/types';

/** One climate survey in its state: draft, in progress or closed (pantallas Eval-Borrador, Eval-Activa, Eval-Cerrada). */
export default function EngagementShow({
    canManage,
    ...props
}: {
    survey: ClimateSurveyCard;
    canManage: boolean;
    draft: ClimateDraft | null;
    unreachable: Paginated<ClimateUnreachable> | null;
    sections: ClimateSection[];
    participation: ClimateParticipation | null;
    resultsUrl: string | null;
    openCommitments: OpenCommitments | null;
}) {
    setLayoutProps({
        breadcrumbs: useEngagementTrail({ title: props.survey.name }),
    });

    return (
        <>
            <Head title={props.survey.name} />
            <div className="flex w-full flex-col gap-8 px-4 py-6 md:px-10 md:py-8">
                <SurveyHeader
                    survey={props.survey}
                    canManage={canManage}
                    participation={props.participation}
                />
                <SurveyBody {...props} />
            </div>
        </>
    );
}
