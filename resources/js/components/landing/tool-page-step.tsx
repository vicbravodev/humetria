import { m } from 'motion/react';
import { cn } from '@/lib/utils';

/** How long each step plays beside the phone before the next one. */
export const STEP_MS = 3500;

type Props = {
    step: { title: string; text: string };
    index: number;
    /** `static`: no phone to play; `playing`: shown now, with its bar filling; `chosen`: shown now, picked or still; `idle`. */
    state: 'static' | 'playing' | 'chosen' | 'idle';
    onPick: () => void;
};

/** One step of «Cómo funciona»: a button that shows it on the phone; the one playing fills a bar along its bottom. */
export default function ToolPageStep({ step, index, state, onPick }: Props) {
    const shown = state === 'playing' || state === 'chosen';

    return (
        <li
            data-reveal="idle"
            className={cn('reveal-rise', `enter-delay-${index * 80}`)}
        >
            <button
                type="button"
                aria-pressed={state === 'static' ? undefined : shown}
                disabled={state === 'static'}
                onClick={onPick}
                data-test={`step-${index}`}
                className={cn(
                    'relative grid w-full gap-2 overflow-hidden rounded-2xl border p-5 text-left transition-[background-color,border-color,box-shadow] duration-200 ease-out disabled:cursor-default',
                    shown
                        ? 'border-border bg-card shadow-raised'
                        : 'border-transparent',
                )}
            >
                <span
                    aria-hidden
                    className="text-4xl leading-none font-semibold tracking-[-0.03em] text-family"
                >
                    {index + 1}
                </span>
                <b className="text-xl tracking-[-0.01em]">{step.title}</b>
                <span className="leading-relaxed text-muted-foreground">
                    {step.text}
                </span>
                {state === 'playing' && (
                    <m.span
                        aria-hidden
                        initial={{ scaleX: 0 }}
                        animate={{ scaleX: 1 }}
                        transition={{
                            duration: STEP_MS / 1000,
                            ease: 'linear',
                        }}
                        className="absolute inset-x-0 bottom-0 h-0.5 origin-left bg-family"
                    />
                )}
            </button>
        </li>
    );
}
