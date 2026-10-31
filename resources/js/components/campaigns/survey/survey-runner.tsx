import { router, usePage } from '@inertiajs/react';
import { Lock } from 'lucide-react';
import { useState } from 'react';
import GuideIntro from '@/components/campaigns/survey/guide-intro';
import SectionProgress from '@/components/campaigns/survey/section-progress';
import SurveyNavigation from '@/components/campaigns/survey/survey-navigation';
import SurveyProgressAside from '@/components/campaigns/survey/survey-progress-aside';
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
    instruments,
    instrument,
    onBack,
}: {
    token: string;
    instruments: SurveyInstrument[];
    instrument: SurveyInstrument;
    onBack: () => void;
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
                onBack={onBack}
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
        <div className="flex w-full max-w-[1028px] justify-center gap-12">
            <SurveyProgressAside
                instruments={instruments}
                current={instrument.key}
                progress={steps.progress}
            />
            <section className="flex max-w-[760px] min-w-0 flex-1 flex-col gap-5 md:gap-2">
                {steps.progress !== undefined && (
                    <SectionProgress
                        guide={instrument.shortTitle}
                        progress={steps.progress}
                    />
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
                <p className="pt-2 text-center text-base text-muted-foreground">
                    <Lock
                        aria-hidden="true"
                        className="mr-1.5 inline size-4 align-[-2px]"
                    />
                    Sus respuestas se guardan en este dispositivo hasta que las
                    envíe.
                    {!instrument.identified &&
                        ' Nadie de su empresa puede ver lo que contesta.'}
                </p>
            </section>
        </div>
    );
}
