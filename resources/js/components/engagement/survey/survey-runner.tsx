import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import SurveyOffline from '@/components/engagement/survey/survey-offline';
import SurveyNavigation from '@/components/engagement/survey/survey-navigation';
import SurveyStep from '@/components/engagement/survey/survey-step';
import FlowProgress from '@/components/humetria/flow-progress';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { useResendWhenOnline } from '@/hooks/use-resend-when-online';
import { useEngagementSurvey } from '@/hooks/use-engagement-survey';
import { submit } from '@/routes/engagement/survey';
import type { EngagementSurveyProps } from '@/types';

/** The survey screen by screen, sent once and whole at the end. Nothing moves on by itself. */
export default function SurveyRunner({
    survey,
    token,
}: {
    survey: EngagementSurveyProps;
    token: string;
}) {
    const flow = useEngagementSurvey(token, survey.sections);
    const [sending, setSending] = useState(false);
    const error = usePage().props.errors['answers'];
    const { step, progress } = flow;
    const isQuestion = step.kind === 'question';
    const isClosed = isQuestion && step.item.scale !== 'open';

    const offline = useResendWhenOnline(() => send());

    function send(): void {
        router.post(submit.url(token), flow.replies(), {
            onStart: () => setSending(true),
            onFinish: () => setSending(false),
            onNetworkError: offline.failed,
            onSuccess: () => {
                offline.sent();
                flow.forget();
            },
        });
    }

    return (
        <section className="flex w-full max-w-[640px] min-w-0 flex-col gap-5">
            {isQuestion && (
                <FlowProgress
                    label={`Pregunta ${progress.position} de ${progress.total}`}
                    left={null}
                    value={progress.position}
                    max={progress.total}
                />
            )}
            {offline.waiting && <SurveyOffline />}
            {error !== undefined && (
                <Alert variant="destructive">
                    <AlertDescription>{error}</AlertDescription>
                </Alert>
            )}
            <SurveyStep survey={survey} flow={flow} />
            <SurveyNavigation
                first={flow.isFirst}
                last={step.kind === 'submit'}
                // A closed question waits for its answer or for «Omitir pregunta»; everything else just goes on.
                canAdvance={
                    !isClosed || flow.answers[step.item.key] !== undefined
                }
                sending={sending}
                onBack={flow.back}
                onNext={step.kind === 'submit' ? send : flow.next}
                onSkip={isQuestion ? () => flow.skip(step.item.key) : null}
            />
        </section>
    );
}
