import { Link } from '@inertiajs/react';
import FirstStepItem from '@/components/first-steps/first-step-item';
import { Button } from '@/components/ui/button';
import { otherToolLabel, progressText } from '@/lib/first-steps/steps';
import type { FirstStep, FirstSteps } from '@/types/first-steps';

/** The open panel: the steps with their progress, the other tools left for later and the way to hide it all, which whoever works as the owner does not get. */
export default function FirstStepsPanel({
    firstSteps,
    onGuide,
    onHide,
}: {
    firstSteps: FirstSteps;
    onGuide: (step: FirstStep) => void;
    onHide?: (() => void) | undefined;
}) {
    return (
        <>
            <header>
                <h2 className="text-base font-semibold">Primeros pasos</h2>
                <p className="text-sm text-muted-foreground">
                    {progressText(firstSteps.steps)}
                </p>
            </header>
            <ol className="mt-1 divide-y">
                {firstSteps.steps.map((step, index) => (
                    <FirstStepItem
                        key={step.key}
                        step={step}
                        index={index}
                        onGuide={onGuide}
                    />
                ))}
            </ol>
            <footer className="mt-1 border-t pt-3 text-sm">
                {firstSteps.others.length > 0 && (
                    <p className="mb-2 text-muted-foreground">
                        También tienes:{' '}
                        {firstSteps.others.map((other, index) => (
                            <span key={other.key}>
                                {index > 0 && ', '}
                                <Link
                                    href={other.href}
                                    className="font-medium text-primary underline-offset-4 hover:underline"
                                >
                                    {otherToolLabel(other.key)}
                                </Link>
                            </span>
                        ))}
                    </p>
                )}
                {onHide !== undefined && (
                    <Button
                        variant="ghost"
                        size="sm"
                        className="-ml-2 text-muted-foreground"
                        onClick={onHide}
                    >
                        Ocultar primeros pasos
                    </Button>
                )}
            </footer>
        </>
    );
}
