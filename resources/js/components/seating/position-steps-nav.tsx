import { Check } from 'lucide-react';
import { POSITION_STEPS } from '@/lib/seating/position-steps';
import { cn } from '@/lib/utils';

/**
 * The five steps of a position on the left (board LS-Puesto): done with a check, the current one ringed. A step opens with a
 * click; the fifth, who holds the position, once it is saved.
 */
export default function PositionStepsNav({
    step,
    saved,
    onStep,
}: {
    step: number;
    saved: boolean;
    onStep: (step: number) => void;
}) {
    return (
        <nav aria-label="Pasos del puesto">
            <ol className="flex flex-col gap-1">
                {POSITION_STEPS.map((each) => {
                    const people = each.number === 5;
                    const current = each.number === step;
                    const mark = cn(
                        'inline-flex size-7 shrink-0 items-center justify-center rounded-full border-[1.5px] border-input text-sm',
                        each.number < step &&
                            'border-primary bg-primary text-primary-foreground',
                        current && 'border-2 border-primary text-primary',
                    );
                    const body = (
                        <>
                            <span aria-hidden className={mark}>
                                {each.number < step ? (
                                    <Check className="size-4" />
                                ) : (
                                    each.number
                                )}
                            </span>
                            <span className="text-left">{each.title}</span>
                        </>
                    );
                    const row = cn(
                        'flex min-h-11 w-full items-center gap-3 rounded-lg px-2.5 py-2 text-sm',
                        current && 'bg-accent font-medium',
                    );

                    return (
                        <li key={each.number}>
                            {people ? (
                                saved ? (
                                    <a href="#quien-lo-ocupa" className={row}>
                                        {body}
                                    </a>
                                ) : (
                                    <span
                                        className={cn(
                                            row,
                                            'text-muted-foreground',
                                        )}
                                    >
                                        {body}
                                    </span>
                                )
                            ) : (
                                <button
                                    type="button"
                                    aria-current={current ? 'step' : undefined}
                                    className={cn(row, 'hover:bg-muted')}
                                    onClick={() => onStep(each.number)}
                                >
                                    {body}
                                </button>
                            )}
                        </li>
                    );
                })}
            </ol>
        </nav>
    );
}
