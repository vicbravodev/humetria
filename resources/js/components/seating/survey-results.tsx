import { Lock } from 'lucide-react';
import AreaFilter from '@/components/humetria/area-filter';
import SurveyQuestionRow from '@/components/seating/survey-question-row';
import { countOf } from '@/lib/plural';
import type { SurveyResults as Results } from '@/types';

/**
 * What the closed survey found (pantalla Eval-Cerrada): the questions counted for the whole center or one area, and
 * how many areas stay out. Under the minimum nothing is shown; the filter narrows the group before the minimum applies.
 */
export default function SurveyResults({
    url,
    results,
    minGroup,
}: {
    url: string;
    results: Results;
    minGroup: number;
}) {
    if (!results.visible) {
        return (
            <section className="max-w-[880px] card p-6">
                <h2 className="flex items-center gap-2 text-xl leading-7 font-semibold tracking-[-0.01em]">
                    <Lock aria-hidden className="size-5 text-primary" />
                    Resultados protegidos
                </h2>
                <p className="mt-2 text-muted-foreground">
                    Contestaron{' '}
                    {countOf(results.answered, 'persona', 'personas')}. Con
                    menos de {minGroup} respuestas no se muestra nada, para que
                    nadie pueda saber quién contestó qué.
                </p>
            </section>
        );
    }

    return (
        <section className="grid max-w-[880px] gap-6 card p-6">
            <div className="flex flex-wrap items-end justify-between gap-4">
                <h2 className="text-xl leading-7 font-semibold tracking-[-0.01em]">
                    {results.area?.label ?? 'Todo el centro de trabajo'}:{' '}
                    {countOf(results.answered, 'respuesta', 'respuestas')}
                </h2>
                <AreaFilter
                    url={url}
                    areas={results.areas.visible}
                    selected={results.area}
                />
            </div>
            <ul className="@container divide-y">
                {results.questions.map((question) => (
                    <SurveyQuestionRow
                        key={question.number}
                        question={question}
                    />
                ))}
            </ul>
            {results.areas.hidden > 0 && (
                <p className="text-sm text-muted-foreground">
                    {countOf(results.areas.hidden, 'área', 'áreas')} no se
                    muestran porque contestaron menos de {minGroup} personas.
                </p>
            )}
        </section>
    );
}
