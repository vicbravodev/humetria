import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import GuideIntro from '@/components/campaigns/survey/guide-intro';
import SectionProgress from '@/components/campaigns/survey/section-progress';
import SurveyNavigation from '@/components/campaigns/survey/survey-navigation';
import SurveyStep from '@/components/campaigns/survey/survey-step';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { useSurveyAnswers } from '@/hooks/use-survey-answers';
import { useSurveySteps } from '@/hooks/use-survey-steps';
import { payload, storageKey } from '@/lib/survey';
import { submit } from '@/routes/survey';
import type { SurveyInstrument } from '@/types';

/** One guide, screen by screen, sent once and whole at the end. Answers wait on the device until then. */
export default function SurveyRunner({
    token,
    instrument,
}: {
    token: string;
    instrument: SurveyInstrument;
}) {
    const [started, setStarted] = useState(false);
    const [sending, setSending] = useState(false);
    const { answers, answer, forget } = useSurveyAnswers(
        storageKey(token, instrument.key),
    );
    const steps = useSurveySteps(instrument, answers);
    const error = usePage().props.errors['answers'];

    if (!started) {
        return (
            <GuideIntro
                instrument={instrument}
                onStart={() => setStarted(true)}
            />
        );
    }

    const send = () =>
        router.post(
            submit.url([token, instrument.key]),
            { answers: payload(instrument, answers) },
            {
                onStart: () => setSending(true),
                onFinish: () => setSending(false),
                onSuccess: forget,
            },
        );

    return (
        <section className="flex flex-col gap-8">
            {steps.progress !== undefined && (
                <SectionProgress progress={steps.progress} />
            )}
            {error !== undefined && (
                <Alert variant="destructive">
                    <AlertDescription>{error}</AlertDescription>
                </Alert>
            )}
            {steps.step !== undefined && (
                <SurveyStep
                    instrument={instrument}
                    step={steps.step}
                    answers={answers}
                    onAnswer={answer}
                />
            )}
            <SurveyNavigation
                isFirst={steps.isFirst}
                isLast={steps.isLast}
                canAdvance={steps.canAdvance}
                sending={sending}
                onBack={steps.back}
                onNext={steps.isLast ? send : steps.next}
            />
            <p className="text-center text-muted-foreground">
                Sus respuestas se guardan en este dispositivo hasta que las
                envíe.
                {!instrument.identified &&
                    ' Nadie de su empresa puede ver lo que contesta.'}
            </p>
        </section>
    );
}
