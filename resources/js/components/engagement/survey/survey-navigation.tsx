import { ArrowLeft, ArrowRight } from 'lucide-react';
import SurveyActions from '@/components/engagement/survey/survey-actions';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

/**
 * The actions of the screen, stuck to the bottom of a phone: «Comenzar» alone on the welcome; then «Anterior» and
 * «Siguiente» in a row («Revisar y enviar» on the last question, «Enviar mis respuestas» on the send step), and
 * «Omitir pregunta» under them as a lighter text action when the question may be left blank. Every button is 44 px or
 * more and nothing moves on by itself.
 */
export default function SurveyNavigation({
    first,
    next,
    canAdvance,
    sending,
    onBack,
    onNext,
    onSkip,
}: {
    first: boolean;
    /** What the main button says past the welcome. */
    next: 'Siguiente' | 'Revisar y enviar' | 'Enviar mis respuestas';
    canAdvance: boolean;
    sending: boolean;
    onBack: () => void;
    onNext: () => void;
    /** Only on a question: leaves it blank and moves on. */
    onSkip: (() => void) | null;
}) {
    if (first) {
        return (
            <SurveyActions>
                <Button
                    size="lg"
                    className="h-14 w-full rounded-[10px] text-[17px]"
                    onClick={onNext}
                >
                    Comenzar
                </Button>
            </SurveyActions>
        );
    }

    return (
        <SurveyActions>
            <div className="flex items-center gap-3">
                <Button
                    variant="ghost"
                    size="lg"
                    className="min-h-12 px-2 text-[17px]"
                    onClick={onBack}
                    disabled={sending}
                >
                    <ArrowLeft aria-hidden="true" />
                    Anterior
                </Button>
                <Button
                    size="lg"
                    className="min-h-[3.25rem] flex-1 rounded-[10px] text-[17px] md:min-w-40 md:flex-none"
                    onClick={onNext}
                    disabled={!canAdvance || sending}
                >
                    {sending && <Spinner />}
                    {next}
                    {next === 'Siguiente' && <ArrowRight aria-hidden="true" />}
                </Button>
            </div>
            {onSkip !== null && (
                <Button
                    variant="link"
                    className="min-h-11 text-base font-medium text-muted-foreground underline underline-offset-[3px]"
                    onClick={onSkip}
                    disabled={sending}
                >
                    Omitir pregunta
                </Button>
            )}
        </SurveyActions>
    );
}
