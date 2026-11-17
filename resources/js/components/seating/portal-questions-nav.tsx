import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

/** «Anterior», «Siguiente» and «Enviar mis respuestas» under the two questions; each waits for its answer. */
export default function PortalQuestionsNav({
    step,
    ready,
    processing,
    onStep,
}: {
    step: 1 | 2;
    ready: boolean;
    processing: boolean;
    onStep: (step: 1 | 2) => void;
}) {
    return (
        <div className="flex gap-3">
            {step === 2 && (
                <Button
                    type="button"
                    size="lg"
                    variant="outline"
                    className="h-14 text-lg"
                    onClick={() => onStep(1)}
                >
                    Anterior
                </Button>
            )}
            {/* Their own keys: React must not turn «Siguiente» into the submit button under the click. */}
            {step === 1 ? (
                <Button
                    key="next"
                    type="button"
                    size="lg"
                    className="h-14 flex-1 text-lg font-semibold"
                    disabled={!ready}
                    onClick={() => onStep(2)}
                >
                    Siguiente
                </Button>
            ) : (
                <Button
                    key="send"
                    size="lg"
                    className="h-14 flex-1 text-lg font-semibold"
                    disabled={processing || !ready}
                >
                    {processing && <Spinner />}
                    Enviar mis respuestas
                </Button>
            )}
        </div>
    );
}
