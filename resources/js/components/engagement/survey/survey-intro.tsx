import { CalendarClock, Lock, MessageSquareOff, Sparkles } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { EngagementSurveyProps } from '@/types';

/**
 * What the person is told before the first question: why, how long, what the system keeps and shows, that every
 * question may be left blank and that open answers go through an AI provider. «Usted», and no promise of secrecy: the
 * system does not keep who answered, and only shows groups of a minimum size.
 */
export default function SurveyIntro({
    survey,
}: {
    survey: EngagementSurveyProps;
}) {
    const { company, closesOn } = survey;
    const title =
        survey.kind === 'pulse' ? 'Encuesta de pulso' : 'Encuesta de clima';
    const minutes = survey.minutes ?? 0;
    const minGroup = survey.minGroup ?? 0;
    const points: { icon: LucideIcon; text: string }[] = [
        {
            icon: MessageSquareOff,
            text: 'Puede dejar sin contestar cualquier pregunta.',
        },
        {
            icon: Sparkles,
            text: 'Lo que escriba en las preguntas abiertas lo procesa un proveedor de inteligencia artificial para resumirlo.',
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
        <section className="flex w-full flex-col gap-[22px]">
            <div className="flex flex-col gap-2.5">
                <h1 className="text-[26px] leading-[34px] font-semibold tracking-[-0.01em]">
                    {title}
                </h1>
                <p>
                    Su opinión ayuda a mejorar el trabajo en {company}. Son unos{' '}
                    {minutes} minutos.
                </p>
            </div>
            <div className="flex items-start gap-3 rounded-xl bg-accent p-4 text-accent-foreground">
                <Lock
                    aria-hidden="true"
                    className="mt-1 size-5 shrink-0 text-primary"
                />
                <p className="text-[17px] leading-[26px]">
                    El sistema no guarda quién contestó qué y solo muestra
                    resultados de grupos de {minGroup} personas o más.
                </p>
            </div>
            <ul className="flex flex-col gap-2.5">
                {points.map(({ icon: Icon, text }) => (
                    <li key={text} className="flex items-start gap-3">
                        <Icon
                            aria-hidden="true"
                            className="mt-1 size-5 shrink-0 text-muted-foreground"
                        />
                        <span>{text}</span>
                    </li>
                ))}
            </ul>
        </section>
    );
}
