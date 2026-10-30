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
    return (
        <section className="flex flex-col gap-5">
            <h1 className="text-3xl leading-[38px] font-semibold tracking-[-0.01em]">
                Evaluación de factores de riesgo psicosocial
            </h1>
            <div className="flex flex-col gap-4 text-lg leading-7">
                <p>
                    {company} aplica esta evaluación para identificar y prevenir
                    los factores de riesgo psicosocial en el trabajo, como lo
                    pide la NOM-035-STPS-2018.
                </p>
                <ul className="flex list-disc flex-col gap-2 pl-6">
                    <li>
                        Sus respuestas son confidenciales y se usan solo para
                        mejorar el ambiente de trabajo.
                    </li>
                    <li>No hay respuestas correctas ni incorrectas.</li>
                    <li>
                        Considere las condiciones de su trabajo de los{' '}
                        <strong className="font-semibold">
                            dos últimos meses
                        </strong>
                        .
                    </li>
                    <li>
                        Conteste todas las preguntas, con calma y con
                        sinceridad: su opinión es lo más importante.
                    </li>
                </ul>
                {closesOn !== null && (
                    <p className="text-muted-foreground">
                        Puede contestar hasta el {closesOn}.
                    </p>
                )}
            </div>
            {children}
        </section>
    );
}
