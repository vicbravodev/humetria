import SurveyActions from '@/components/campaigns/survey/survey-actions';
import SurveyArc from '@/components/campaigns/survey/survey-arc';
import { Button } from '@/components/ui/button';
import { percentLabel } from '@/lib/chart';
import type { SurveyInstrument } from '@/types';

/**
 * Between guides (board «Guía enviada»): the guide just sent is confirmed, the arch shows how many of the person's guides
 * went out, what is left and how long, and the choice to go on now or come back later with the same link. The same words
 * for everyone: it never says whether anyone needs a clinical assessment.
 */
export default function GuideSent({
    sent,
    next,
    done,
    total,
    onContinue,
    onLater,
}: {
    sent: SurveyInstrument;
    next: SurveyInstrument;
    done: number;
    total: number;
    onContinue: () => void;
    onLater: () => void;
}) {
    return (
        <div className="flex w-full max-w-2xl flex-col">
            <section
                role="status"
                className="flex flex-1 flex-col items-center justify-center gap-4 pb-[8dvh] text-center"
            >
                <SurveyArc
                    value={done}
                    max={total}
                    label={`${done} de ${total} guías enviadas`}
                >
                    <span className="text-[2rem] leading-none font-semibold tracking-[-0.03em] tabular-nums">
                        {percentLabel(done, total)}
                    </span>
                    <span className="text-sm font-medium text-muted-foreground">
                        {done} de {total}
                    </span>
                </SurveyArc>
                <h1 className="text-[1.625rem] leading-[2.125rem] font-semibold tracking-[-0.01em]">
                    {sent.shortTitle} enviada
                </h1>
                <p className="max-w-xs text-muted-foreground">
                    Gracias. Le falta la {next.shortTitle}, de unos{' '}
                    {next.minutes} minutos. Puede seguir ahora o volver después
                    con este mismo enlace.
                </p>
            </section>
            <SurveyActions>
                <Button
                    size="lg"
                    className="h-14 w-full rounded-[10px] text-[1.0625rem]"
                    onClick={onContinue}
                >
                    Continuar con la {next.shortTitle}
                </Button>
                <Button
                    variant="ghost"
                    size="lg"
                    className="h-12 w-full text-[1.0625rem] text-primary"
                    onClick={onLater}
                >
                    Seguir después
                </Button>
            </SurveyActions>
        </div>
    );
}
