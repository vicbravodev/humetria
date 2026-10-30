import { ArrowLeft, ArrowRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

/** "Anterior" in ghost and a large "Siguiente" on the right; on the last screen it sends the guide. */
export default function SurveyNavigation({
    isFirst,
    isLast,
    canAdvance,
    sending,
    onBack,
    onNext,
}: {
    isFirst: boolean;
    isLast: boolean;
    canAdvance: boolean;
    sending: boolean;
    onBack: () => void;
    onNext: () => void;
}) {
    return (
        <div className="flex items-center justify-between gap-4">
            <Button
                variant="ghost"
                size="lg"
                onClick={onBack}
                disabled={isFirst || sending}
            >
                <ArrowLeft aria-hidden="true" />
                Anterior
            </Button>
            <Button
                size="lg"
                className="h-14 min-w-44 text-lg"
                onClick={onNext}
                disabled={!canAdvance || sending}
            >
                {sending && <Spinner />}
                {isLast ? 'Enviar respuestas' : 'Siguiente'}
                {!isLast && <ArrowRight aria-hidden="true" />}
            </Button>
        </div>
    );
}
