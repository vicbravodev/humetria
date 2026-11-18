import { CalendarClock, Clock, Lock, MessageSquareOff } from 'lucide-react';
import IntroChip from '@/components/engagement/survey/intro-chip';
import { surveyPrivacyText } from '@/lib/engagement/results';
import type { EngagementSurveyProps } from '@/types';

/**
 * What the person is told before the first question, ordered as NOM-035's welcome: what it is and how many questions,
 * why, what the system keeps and shows, that every question may be left blank, how long and until when. «Usted», and no
 * promise of secrecy: the system does not keep who answered, and only shows groups of a minimum size. That an AI provider
 * summarizes the comments is said at the open question, where the person writes.
 */
export default function SurveyIntro({
    survey,
}: {
    survey: EngagementSurveyProps;
}) {
    const { company, closesOn } = survey;
    const pulse = survey.kind === 'pulse';
    const questions = survey.sections.reduce(
        (total, section) => total + section.items.length,
        0,
    );

    return (
        <section className="flex w-full flex-col gap-5 text-base leading-6">
            <div>
                <p className="mb-1.5 text-caption font-medium tracking-[0.02em] text-primary uppercase">
                    {pulse ? 'Pulso' : 'Clima'} · {questions} preguntas
                </p>
                <h1 className="text-[1.625rem] leading-[2.125rem] font-semibold tracking-[-0.01em]">
                    {pulse ? 'Encuesta de pulso' : 'Encuesta de clima'}
                </h1>
                <p className="mt-2 text-muted-foreground">
                    Su opinión ayuda a mejorar el trabajo en {company}.
                </p>
            </div>
            <div className="flex items-start gap-3 rounded-xl bg-accent px-4 py-3.5 text-accent-foreground">
                <Lock
                    aria-hidden="true"
                    className="mt-0.5 size-5 shrink-0 text-primary"
                />
                <p className="font-medium">
                    {surveyPrivacyText(survey.minGroup ?? 0)}
                </p>
            </div>
            <div className="flex items-start gap-3">
                <MessageSquareOff
                    aria-hidden="true"
                    className="mt-0.5 size-5 shrink-0 text-primary"
                />
                <p>
                    <strong className="block font-semibold">
                        Puede dejar preguntas sin contestar.
                    </strong>
                    <span className="text-muted-foreground">
                        Cualquier pregunta se puede omitir.
                    </span>
                </p>
            </div>
            <div className="flex flex-wrap gap-2">
                {survey.minutes !== null && (
                    <IntroChip icon={Clock}>
                        Unos {survey.minutes} min
                    </IntroChip>
                )}
                {closesOn !== null && (
                    <IntroChip icon={CalendarClock}>
                        Hasta el {closesOn}
                    </IntroChip>
                )}
            </div>
        </section>
    );
}
