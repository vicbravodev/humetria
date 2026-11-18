import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AnswersKeptNote from '@/components/campaigns/survey/answers-kept-note';
import GuideIntro from '@/components/campaigns/survey/guide-intro';
import SectionProgress from '@/components/campaigns/survey/section-progress';
import SurveyOffline from '@/components/campaigns/survey/survey-offline';
import SurveyNavigation from '@/components/campaigns/survey/survey-navigation';
import SurveyProgressAside from '@/components/campaigns/survey/survey-progress-aside';
import SurveyStep from '@/components/campaigns/survey/survey-step';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { useResendWhenOnline } from '@/hooks/use-resend-when-online';
import { useSurveyAnswers } from '@/hooks/use-survey-answers';
import { useSurveySteps } from '@/hooks/use-survey-steps';
import { payload, storageKey } from '@/lib/survey';
import type { IntroCopy } from '@/lib/survey';
import { submit } from '@/routes/survey';
import type { SurveyInstrument } from '@/types';

/** One guide, screen by screen, sent once and whole at the end. Answers wait on the device until then. */
export default function SurveyRunner({
    token,
    instruments,
    instrument,
    noun,
    minGroup,
    onBack,
    onSent,
}: {
    token: string;
    instruments: SurveyInstrument[];
    instrument: SurveyInstrument;
    noun: IntroCopy['noun'];
    minGroup: number | null;
    onBack: () => void;
    /** Told as the guide goes out, so the page can confirm it once the next one is shown. */
    onSent: (instrument: string) => void;
}) {
    const [started, setStarted] = useState(false);
    const [sending, setSending] = useState(false);
    const { answers, answer, forget } = useSurveyAnswers(
        storageKey(token, instrument.key),
    );
    const steps = useSurveySteps(instrument, answers);
    const error = usePage().props.errors['answers'];

    const offline = useResendWhenOnline(() => send());

    function send(): void {
        router.post(
            submit.url([token, instrument.key]),
            { answers: payload(instrument, answers) },
            {
                // The page stays mounted through the redirect, so it remembers which guide was just sent.
                preserveState: true,
                onStart: () => {
                    setSending(true);
                    onSent(instrument.key);
                },
                onFinish: () => setSending(false),
                onNetworkError: offline.failed,
                onSuccess: () => {
                    offline.sent();
                    forget();
                },
            },
        );
    }

    if (!started) {
        return (
            <GuideIntro
                instrument={instrument}
                noun={noun}
                minGroup={minGroup}
                onBack={onBack}
                onStart={() => setStarted(true)}
            />
        );
    }

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
                {offline.waiting && <SurveyOffline />}
                {error !== undefined && (
                    <Alert variant="destructive">
                        <AlertDescription>{error}</AlertDescription>
                    </Alert>
                )}
                {/* The screen's own heading, for whoever reads with a screen reader: the questions themselves are not headings. */}
                <h1 className="sr-only">{instrument.title}</h1>
                {steps.step !== undefined && (
                    <SurveyStep
                        // A new screen is a new element: its heading takes the focus as it mounts.
                        key={steps.step.keys[0]}
                        instrument={instrument}
                        step={steps.step}
                        answers={answers}
                        onAnswer={answer}
                    />
                )}
                <AnswersKeptNote identified={instrument.identified} />
                <SurveyNavigation
                    isLast={steps.isLast}
                    canAdvance={steps.canAdvance}
                    sending={sending}
                    onBack={
                        steps.isFirst ? () => setStarted(false) : steps.back
                    }
                    onNext={steps.isLast ? send : steps.next}
                />
            </section>
        </div>
    );
}
