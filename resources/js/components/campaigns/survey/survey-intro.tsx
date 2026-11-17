import { CalendarClock, Clock, Lock } from 'lucide-react';
import type { ReactNode } from 'react';
import BeforeStart from '@/components/campaigns/survey/before-start';
import IntroChip from '@/components/campaigns/survey/intro-chip';
import type { IntroCopy } from '@/lib/survey';

/**
 * What the norm asks to say before the questionnaire (II.2 a, III.2 a): the objective, that it is confidential and
 * used only to improve the work environment, that there are no right or wrong answers, that every question must be
 * answered, and to think of the last two months (criterio C-17). Nothing of it is left out; it is ordered: what it is,
 * the confidentiality, «Antes de empezar», then how long and until when.
 */
export default function SurveyIntro({
    company,
    context,
    closesOn,
    minutes,
    copy,
    children,
}: {
    company: string;
    /** The line above the title: «Evaluación NOM-035 · 2 guías». */
    context: string | null;
    closesOn: string | null;
    /** About how long the guides still to answer take; `together` when they are more than one. */
    minutes: { total: number; together: boolean };
    copy: IntroCopy;
    children?: ReactNode;
}) {
    return (
        <section className="flex w-full max-w-2xl flex-col gap-5 text-base leading-6">
            <div>
                {context !== null && (
                    <p className="mb-1.5 text-caption font-medium tracking-[0.02em] text-primary uppercase">
                        {context}
                    </p>
                )}
                <h1 className="text-[1.625rem] leading-[2.125rem] font-semibold tracking-[-0.01em]">
                    {copy.title}
                </h1>
                <p className="mt-2 text-muted-foreground">
                    {company} {copy.purpose}
                </p>
            </div>
            <div className="flex items-start gap-3 rounded-xl bg-accent px-4 py-3.5 text-accent-foreground">
                <Lock
                    aria-hidden="true"
                    className="mt-0.5 size-5 shrink-0 text-primary"
                />
                <p className="font-medium">
                    Sus respuestas son confidenciales y se usan solo para
                    mejorar el ambiente de trabajo.
                </p>
            </div>
            <BeforeStart period={copy.period} />
            <div className="flex flex-wrap gap-2">
                {minutes.total > 0 && (
                    <IntroChip icon={Clock}>
                        Unos {minutes.total} min
                        {minutes.together ? ' en total' : ''}
                    </IntroChip>
                )}
                {closesOn !== null && (
                    <IntroChip icon={CalendarClock}>
                        Hasta el {closesOn}
                    </IntroChip>
                )}
            </div>
            {children}
        </section>
    );
}
