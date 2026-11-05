import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { POSITION_STEPS } from '@/lib/seating/position-steps';

/**
 * «Anterior» and «Siguiente: …» of the position (board LS-Puesto); the last step saves it. A saved position may be saved
 * from any step.
 */
export default function PositionWizardFooter({
    step,
    saved,
    processing,
    onStep,
}: {
    step: number;
    saved: boolean;
    processing: boolean;
    onStep: (step: number) => void;
}) {
    const last = step === 4;

    return (
        <div className="flex flex-wrap items-center justify-between gap-3 border-t pt-5">
            {step > 1 ? (
                <Button
                    type="button"
                    variant="outline"
                    onClick={() => onStep(step - 1)}
                >
                    Anterior
                </Button>
            ) : (
                <span />
            )}
            <div className="flex flex-wrap gap-2">
                {saved && !last && (
                    <Button variant="outline" disabled={processing}>
                        Guardar puesto
                    </Button>
                )}
                {/* Their own keys: React must not turn «Siguiente» into the submit button under the click. */}
                {last ? (
                    <Button key="save" disabled={processing}>
                        {processing && <Spinner />}
                        Guardar puesto
                    </Button>
                ) : (
                    <Button
                        key="next"
                        type="button"
                        data-test="position-next"
                        onClick={() => onStep(step + 1)}
                    >
                        {POSITION_STEPS[step - 1]?.next}
                    </Button>
                )}
            </div>
        </div>
    );
}
