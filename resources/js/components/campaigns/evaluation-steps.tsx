import { Check } from 'lucide-react';
import { Fragment } from 'react';
import { cn } from '@/lib/utils';

const STEPS = ['Centro', 'Guías y muestra', 'Fecha y envío'];

/**
 * Where the new-evaluation assistant stands (pantalla NOM035-Nueva). The last step, the date and the sending, happens
 * on the draft the assistant creates.
 */
export default function EvaluationSteps({ current }: { current: number }) {
    return (
        <ol className="flex items-center gap-2.5">
            {STEPS.map((step, index) => {
                const number = index + 1;
                const done = number < current;
                const reached = number <= current;

                return (
                    <Fragment key={step}>
                        {index > 0 && (
                            <li
                                aria-hidden
                                className="h-px min-w-4 grow bg-border"
                            />
                        )}
                        <li
                            className="flex items-center gap-2"
                            aria-current={
                                number === current ? 'step' : undefined
                            }
                        >
                            <span
                                className={cn(
                                    'inline-flex size-6 shrink-0 items-center justify-center rounded-full text-xs font-semibold',
                                    reached
                                        ? 'bg-primary text-primary-foreground'
                                        : 'border-[1.5px] border-input text-muted-foreground',
                                )}
                            >
                                {done ? (
                                    <Check aria-hidden className="size-3.5" />
                                ) : (
                                    number
                                )}
                                {done && (
                                    <span className="sr-only">Hecho: </span>
                                )}
                            </span>
                            <span
                                className={cn(
                                    'text-sm',
                                    number === current
                                        ? 'font-medium'
                                        : 'text-muted-foreground max-sm:sr-only',
                                )}
                            >
                                {step}
                            </span>
                        </li>
                    </Fragment>
                );
            })}
        </ol>
    );
}
