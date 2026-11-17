import { ArrowLeft, ArrowRight } from 'lucide-react';
import SurveyActions from '@/components/campaigns/survey/survey-actions';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

/**
 * "Anterior" in ghost and a large "Siguiente" on the right, stuck to the bottom of a phone; on the last screen it sends the
 * guide. On the first screen "Anterior" goes back to the guide's introduction.
 */
export default function SurveyNavigation({
    isLast,
    canAdvance,
    sending,
    onBack,
    onNext,
}: {
    isLast: boolean;
    canAdvance: boolean;
    sending: boolean;
    onBack: () => void;
    onNext: () => void;
}) {
    return (
        <SurveyActions>
            <div className="flex flex-wrap items-center justify-between gap-3">
                <Button
                    variant="ghost"
                    size="lg"
                    className="px-3 text-[1.0625rem]"
                    onClick={onBack}
                    disabled={sending}
                >
                    <ArrowLeft aria-hidden="true" />
                    Anterior
                </Button>
                <Button
                    size="lg"
                    className="h-[3.25rem] min-w-40 flex-1 rounded-[10px] text-[1.0625rem] md:h-10 md:flex-none"
                    onClick={onNext}
                    disabled={!canAdvance || sending}
                >
                    {sending && <Spinner />}
                    {isLast ? 'Enviar respuestas' : 'Siguiente'}
                    {!isLast && <ArrowRight aria-hidden="true" />}
                </Button>
            </div>
        </SurveyActions>
    );
}
