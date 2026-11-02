import ProgressBar from '@/components/humetria/progress-bar';
import SurveyCloseDialog from '@/components/seating/survey-close-dialog';
import { countOf } from '@/lib/plural';
import type { SurveyRecord } from '@/types';

/** How far the survey has reached while people answer: a count, never who. Results wait for the close. */
export default function SurveyProgress({
    survey,
    workCenterId,
}: {
    survey: SurveyRecord;
    workCenterId: string;
}) {
    return (
        <section className="grid max-w-[880px] gap-3 card p-6">
            <h2 className="text-xl leading-7 font-semibold tracking-[-0.01em]">
                {survey.status === 'active'
                    ? 'La encuesta está en curso'
                    : 'Encuesta cerrada'}
            </h2>
            <ProgressBar
                value={survey.answered}
                max={survey.participants}
                label="Personas que han contestado"
            />
            <p className="text-muted-foreground tabular-nums">
                Han contestado {survey.answered} de{' '}
                {countOf(survey.participants, 'persona', 'personas')}
                {survey.closesOn !== null && `. Cierra el ${survey.closesOn}`}.
            </p>
            {survey.status === 'active' && (
                <p className="text-sm text-muted-foreground">
                    Los resultados aparecen al cerrar la encuesta: así nadie
                    puede deducir quién contestó mirando cómo cambian los
                    números.
                </p>
            )}
            {survey.status === 'active' && (
                <SurveyCloseDialog workCenterId={workCenterId} />
            )}
        </section>
    );
}
