import { Check } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { stepCopy } from '@/lib/first-steps/steps';
import type { FirstStep } from '@/types/first-steps';

/** One step of the list: a check when it is done, its number when it is not, and the button that guides the owner through it. */
export default function FirstStepItem({
    step,
    index,
    onGuide,
}: {
    step: FirstStep;
    index: number;
    onGuide: (step: FirstStep) => void;
}) {
    const { title, text } = stepCopy(step);

    return (
        <li className="flex items-start gap-3 py-3">
            {step.done ? (
                <span className="flex size-6 shrink-0 items-center justify-center rounded-full bg-primary text-primary-foreground">
                    <Check aria-hidden className="size-4" />
                    <span className="sr-only">Hecho:</span>
                </span>
            ) : (
                <span
                    aria-hidden
                    className="flex size-6 shrink-0 items-center justify-center rounded-full border text-xs font-medium text-muted-foreground"
                >
                    {index + 1}
                </span>
            )}
            <div className="min-w-0 flex-1">
                <p className="text-sm font-medium">{title}</p>
                <p className="text-sm text-muted-foreground">{text}</p>
                {!step.done && (
                    <Button
                        size="sm"
                        variant="outline"
                        className="mt-2"
                        onClick={() => onGuide(step)}
                    >
                        Guíame
                    </Button>
                )}
            </div>
        </li>
    );
}
