import { ArrowLeft, ArrowRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

/**
 * «Anterior» and «Siguiente» (on the last screen «Enviar mis respuestas»), and «Omitir pregunta» under them when the
 * question may be left blank. Every button is 44 px or more and nothing moves on by itself.
 */
export default function SurveyNavigation({
    first,
    last,
    canAdvance,
    sending,
    onBack,
    onNext,
    onSkip,
}: {
    first: boolean;
    last: boolean;
    canAdvance: boolean;
    sending: boolean;
    onBack: () => void;
    onNext: () => void;
    /** Only on a question: leaves it blank and moves on. */
    onSkip: (() => void) | null;
}) {
    return (
        <div className="flex flex-col gap-3 pt-2">
            <div className="flex items-center justify-between gap-3">
                <Button
                    variant="ghost"
                    size="lg"
                    className="min-h-11 px-3 text-[17px]"
                    onClick={onBack}
                    disabled={first || sending}
                >
                    <ArrowLeft aria-hidden="true" />
                    Anterior
                </Button>
                <Button
                    size="lg"
                    className="min-h-11 min-w-40 rounded-[10px] text-[17px]"
                    onClick={onNext}
                    disabled={!canAdvance || sending}
                >
                    {sending && <Spinner />}
                    {last ? 'Enviar mis respuestas' : 'Siguiente'}
                    {!last && <ArrowRight aria-hidden="true" />}
                </Button>
            </div>
            {onSkip !== null && (
                <Button
                    variant="outline"
                    size="lg"
                    className="min-h-11 w-full text-[17px]"
                    onClick={onSkip}
                    disabled={sending}
                >
                    Omitir pregunta
                </Button>
            )}
        </div>
    );
}
