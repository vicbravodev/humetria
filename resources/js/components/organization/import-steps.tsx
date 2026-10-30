import { Check } from 'lucide-react';
import { Fragment } from 'react';
import { importStepState } from '@/lib/employees';
import { cn } from '@/lib/utils';

const STEPS = ['Sube el archivo', 'Revisa', 'Confirma'];

const MARK = {
    done: 'bg-primary text-primary-foreground',
    current: 'bg-primary text-primary-foreground',
    next: 'border-[1.5px] border-input text-muted-foreground',
};

const LABEL = {
    done: 'font-medium max-sm:sr-only',
    current: 'font-semibold',
    next: 'text-muted-foreground max-sm:sr-only',
};

/** Where the upload stands: the steps done carry a check, the current one is in bold. */
export default function ImportSteps({ step }: { step: 1 | 2 | 3 }) {
    return (
        <ol
            aria-label="Pasos"
            className="flex max-w-[640px] items-center gap-3"
        >
            {STEPS.map((label, index) => {
                const state = importStepState(index + 1, step);

                return (
                    <Fragment key={label}>
                        {index > 0 && (
                            <li
                                aria-hidden
                                className={cn(
                                    'h-0.5 min-w-4 flex-1 rounded-full',
                                    state === 'next'
                                        ? 'bg-border'
                                        : 'bg-primary',
                                )}
                            />
                        )}
                        <li
                            aria-current={
                                state === 'current' ? 'step' : undefined
                            }
                            className="flex shrink-0 items-center gap-2.5"
                        >
                            <span
                                className={cn(
                                    'inline-flex size-7 items-center justify-center rounded-full text-[13px] font-semibold',
                                    MARK[state],
                                )}
                            >
                                {state === 'done' ? (
                                    <Check aria-hidden className="size-4" />
                                ) : (
                                    index + 1
                                )}
                            </span>
                            <span
                                className={cn(
                                    'text-sm leading-5',
                                    LABEL[state],
                                )}
                            >
                                {label}
                                {state === 'done' && (
                                    <span className="sr-only"> (hecho)</span>
                                )}
                            </span>
                        </li>
                    </Fragment>
                );
            })}
        </ol>
    );
}
