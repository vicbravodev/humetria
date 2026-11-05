import { ArrowLeft } from 'lucide-react';
import { Button } from '@/components/ui/button';
import type { StepId } from '@/lib/complaints/form';
import { STEP_COUNT, stepNumber } from '@/lib/complaints/form';

/** Where the person is (board V4-Quejas-Form): «Paso 2 de 4», its bar, and the way back. */
export default function StepProgress({
    step,
    onBack,
}: {
    step: StepId;
    onBack: () => void;
}) {
    const number = stepNumber(step);

    return (
        <div className="flex flex-col gap-2">
            <div className="flex items-center justify-between text-sm">
                <Button
                    type="button"
                    variant="ghost"
                    className="-ml-3"
                    onClick={onBack}
                >
                    <ArrowLeft aria-hidden />
                    Atrás
                </Button>
                <span className="text-muted-foreground">
                    Paso {number} de {STEP_COUNT}
                </span>
            </div>
            <div
                aria-hidden
                className="h-1.5 overflow-hidden rounded-full bg-track"
            >
                <div
                    className="h-full rounded-full bg-primary"
                    style={{ width: `${(number / STEP_COUNT) * 100}%` }}
                />
            </div>
        </div>
    );
}
