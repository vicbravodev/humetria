import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import type { StepId } from '@/lib/complaints/form';
import { STEPS } from '@/lib/complaints/form';

/** Back and forward, and where the person is: «Paso 2 de 4». The last step sends; none advances on its own. */
export default function StepNav({
    step,
    problem,
    processing,
    onBack,
    onNext,
    onSend,
}: {
    step: StepId;
    problem: string | undefined;
    processing: boolean;
    onBack: () => void;
    onNext: () => void;
    onSend: () => void;
}) {
    const last = step === 'review';
    const position = STEPS.indexOf(step);

    return (
        <div className="flex flex-col gap-3">
            {problem !== undefined && (
                <p role="alert" className="text-sm text-destructive">
                    {problem}
                </p>
            )}
            <div className="flex flex-wrap items-center justify-between gap-3">
                <p className="text-base text-muted-foreground">
                    Paso {position} de {STEPS.length - 1}
                </p>
                <div className="flex gap-2">
                    {step !== 'type' && (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onBack}
                        >
                            Atrás
                        </Button>
                    )}
                    <Button
                        type="button"
                        disabled={processing}
                        onClick={last ? onSend : onNext}
                    >
                        {processing && <Spinner />}
                        {last ? 'Enviar mi queja' : 'Continuar'}
                    </Button>
                </div>
            </div>
        </div>
    );
}
