import {
    CalendarClock,
    CalendarRange,
    CircleCheck,
    ListChecks,
    Lock,
} from 'lucide-react';
import type { ReactNode } from 'react';

/**
 * What the norm asks to say before the questionnaire (II.2 a, III.2 a): the objective, that it is confidential and
 * used only to improve the work environment, that there are no right or wrong answers, that every question must be
 * answered, and to think of the last two months (criterio C-17).
 */
export default function SurveyIntro({
    company,
    closesOn,
    children,
}: {
    company: string;
    closesOn: string | null;
    children?: ReactNode;
}) {
    const points = [
        {
            icon: CircleCheck,
            text: 'No hay respuestas correctas ni incorrectas.',
        },
        {
            icon: CalendarRange,
            text: (
                <>
                    Considere las condiciones de su trabajo de los{' '}
                    <strong className="font-semibold">dos últimos meses</strong>
                    .
                </>
            ),
        },
        {
            icon: ListChecks,
            text: 'Conteste todas las preguntas, con calma y con sinceridad: su opinión es lo más importante.',
        },
        ...(closesOn === null
            ? []
            : [
                  {
                      icon: CalendarClock,
                      text: `Puede contestar hasta el ${closesOn}.`,
                  },
              ]),
    ];

    return (
        <section className="flex w-full max-w-2xl flex-col gap-[22px]">
            <div className="flex flex-col gap-2.5">
                <h1 className="text-[26px] leading-[34px] font-semibold tracking-[-0.01em]">
                    Evaluación de factores de riesgo psicosocial
                </h1>
                <p>
                    {company} aplica esta evaluación para identificar y prevenir
                    los factores de riesgo psicosocial en el trabajo, como lo
                    pide la NOM-035-STPS-2018.
                </p>
            </div>
            <div className="flex items-start gap-3 rounded-xl bg-accent p-4 text-accent-foreground">
                <Lock
                    aria-hidden="true"
                    className="mt-1 size-5 shrink-0 text-primary"
                />
                <p className="text-[17px] leading-[26px]">
                    Sus respuestas son confidenciales y se usan solo para
                    mejorar el ambiente de trabajo.
                </p>
            </div>
            <ul className="flex flex-col gap-2.5">
                {points.map(({ icon: Icon, text }, index) => (
                    <li key={index} className="flex items-start gap-3">
                        <Icon
                            aria-hidden="true"
                            className="mt-1 size-5 shrink-0 text-muted-foreground"
                        />
                        <span>{text}</span>
                    </li>
                ))}
            </ul>
            {children}
        </section>
    );
}
