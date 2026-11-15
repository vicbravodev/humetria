import { Head, setLayoutProps } from '@inertiajs/react';
import { Hourglass, Target } from 'lucide-react';
import CommitmentsBoard from '@/components/engagement/commitments/commitments-board';
import PublishFeedbackButton from '@/components/engagement/commitments/publish-feedback-button';
import LoopActionCard from '@/components/engagement/results/loop-action-card';
import ResultsNav from '@/components/engagement/results/results-nav';
import EmptyState from '@/components/humetria/empty-state';
import PageHeader from '@/components/humetria/page-header';
import { Badge } from '@/components/ui/badge';
import {
    useEngagementTrail,
    useSurveyCrumb,
} from '@/hooks/use-engagement-routes';
import { countOf } from '@/lib/plural';
import type { EngagementCommitments } from '@/types';

/** «Nos dijeron / Haremos» of a closed survey (pantalla Clima · Compromisos): the cycle closes here and in the portal. */
export default function Commitments(props: EngagementCommitments) {
    const { survey, available, counts } = props;
    setLayoutProps({
        breadcrumbs: useEngagementTrail(useSurveyCrumb(survey), {
            title: 'Compromisos',
        }),
    });

    return (
        <>
            <Head title={`Compromisos · ${survey.name}`} />
            <div className="flex w-full flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <PageHeader
                    caption={survey.name}
                    title="Nos dijeron / Haremos"
                    status={
                        props.published ? (
                            <Badge variant="success">
                                Publicado en el portal
                            </Badge>
                        ) : (
                            <Badge variant="secondary">Sin publicar</Badge>
                        )
                    }
                    facts={[
                        {
                            icon: Target,
                            text: `${countOf(counts.all, 'compromiso', 'compromisos')} · un grupo tiene a lo más 3`,
                        },
                    ]}
                >
                    {available &&
                        props.canManage &&
                        !props.published &&
                        counts.all > counts.dropped && (
                            <PublishFeedbackButton surveyId={survey.id} />
                        )}
                </PageHeader>
                {available ? (
                    <>
                        <ResultsNav
                            surveyId={survey.id}
                            active="commitments"
                            commitments={
                                counts.all > 0 ? counts.all : undefined
                            }
                        />
                        {props.loopAction !== null && (
                            <LoopActionCard loopAction={props.loopAction} />
                        )}
                        <CommitmentsBoard {...props} />
                    </>
                ) : (
                    <EmptyState
                        icon={Hourglass}
                        title="Los compromisos se registran al cerrar la encuesta"
                    >
                        Antes de que cierre no hay resultados que atender, y
                        cada grupo se muestra solo cuando ya es lo bastante
                        grande para no señalar a nadie.
                    </EmptyState>
                )}
            </div>
        </>
    );
}
