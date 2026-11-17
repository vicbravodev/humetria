import { Lock } from 'lucide-react';
import SurveyArc from '@/components/engagement/survey/survey-arc';
import { Button } from '@/components/ui/button';
import { percentLabel } from '@/lib/chart';
import { focusOnMount } from '@/lib/focus';
import type { Tally } from '@/lib/engagement/survey-flow';

/**
 * The step before sending (board «Pulso: paso de envío»), centered: the arch with how many questions were answered, that
 * the answers cannot change once sent, that the system does not keep who answered what, and the way back to a question
 * left blank. It only counts: no answer is shown here, and nothing is sent until «Enviar mis respuestas».
 */
export default function ReadyToSend({
    tally,
    onReview,
}: {
    tally: Tally;
    onReview: () => void;
}) {
    const { answered, total } = tally;

    return (
        <section className="flex flex-1 flex-col items-center justify-center gap-4 py-6 text-center">
            <SurveyArc
                value={answered}
                max={total}
                label={`${answered} de ${total} preguntas contestadas`}
            >
                <span className="text-[2rem] leading-none font-semibold tracking-[-0.03em] tabular-nums">
                    {percentLabel(answered, total)}
                </span>
                <span className="text-sm font-medium text-muted-foreground">
                    {answered} de {total}
                </span>
            </SurveyArc>
            <h1
                ref={focusOnMount}
                tabIndex={-1}
                className="text-[1.625rem] leading-[2.125rem] font-semibold tracking-[-0.01em] focus:outline-none"
            >
                Listo para enviar
            </h1>
            <p className="max-w-xs text-[17px] leading-[26px] text-muted-foreground">
                Contestó {answered} de {total} preguntas. Cuando envíe sus
                respuestas ya no podrá cambiarlas.
            </p>
            <p className="flex max-w-xs items-center gap-2 rounded-xl bg-accent px-3.5 py-2.5 text-left text-base text-accent-foreground">
                <Lock
                    aria-hidden="true"
                    className="size-4 shrink-0 text-primary"
                />
                El sistema no guarda quién contestó qué.
            </p>
            {tally.firstOmitted !== null && (
                <Button
                    variant="link"
                    className="min-h-11 text-base font-medium underline underline-offset-[3px]"
                    onClick={onReview}
                >
                    Revisar la pregunta que omití
                </Button>
            )}
        </section>
    );
}
