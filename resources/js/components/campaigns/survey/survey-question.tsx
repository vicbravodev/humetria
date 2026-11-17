import type { Ref } from 'react';
import { cn } from '@/lib/utils';
import type { SurveyOption } from '@/types';

/**
 * One question with its options (design system: SurveyQuestion): full-width 56 px rows on a phone, side by side on a
 * computer. The chosen option gets a 2 px primary border, the accent fill and weight 500: three signals, not color
 * alone. Choosing never moves on.
 */
export default function SurveyQuestion({
    name,
    caption,
    text,
    options,
    value,
    onChange,
    legendRef,
    paired = false,
}: {
    name: string;
    caption?: string | undefined;
    text: string;
    options: SurveyOption[];
    value: string | undefined;
    onChange: (value: string) => void;
    /** For the question a screen opens with alone (a gate): its legend takes the focus. */
    legendRef?: Ref<HTMLLegendElement>;
    /** One event of a list asked whole (Guía I, section I): a shorter line and Sí / No side by side, as on paper. */
    paired?: boolean;
}) {
    return (
        // The separator sits on a wrapper: on the fieldset itself the legend would cut it.
        <div
            className={cn(
                'md:py-6 md:[&+&]:border-t',
                paired && 'pt-3.5 [&+&]:border-t md:pt-5 md:pb-0',
            )}
        >
            <fieldset className="flex flex-col gap-2.5 md:gap-3.5">
                <legend
                    ref={legendRef}
                    tabIndex={legendRef === undefined ? undefined : -1}
                    className={cn(
                        'flex flex-col gap-2 focus:outline-none',
                        paired ? 'mb-2.5' : 'mb-3.5',
                    )}
                >
                    {caption !== undefined && (
                        <span className="text-base text-muted-foreground">
                            {caption}
                        </span>
                    )}
                    <span
                        className={cn(
                            'font-medium',
                            paired
                                ? 'text-[1.0625rem] leading-[1.625rem]'
                                : 'text-xl leading-[1.875rem] tracking-[-0.005em]',
                        )}
                    >
                        {text}
                    </span>
                </legend>
                <div
                    className={cn(
                        'flex flex-col gap-2.5 md:flex-row md:gap-2',
                        paired && 'grid grid-cols-2 md:flex',
                    )}
                >
                    {options.map((option) => (
                        <label
                            key={option.value}
                            className={cn(
                                'flex cursor-pointer items-center gap-3 rounded-lg border border-input bg-card px-4 py-3 text-lg leading-7 transition-[border-color,background-color] duration-150 hover:bg-muted',
                                paired ? 'min-h-12' : 'min-h-14',
                                'md:flex-1 md:basis-0 md:flex-col md:justify-center md:gap-2 md:px-2 md:py-3.5 md:text-center md:text-base md:leading-[1.375rem]',
                                'has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-ring',
                                value === option.value &&
                                    'border-2 border-primary bg-accent px-[15px] py-[11px] font-medium hover:bg-accent md:px-[7px] md:py-[13px]',
                            )}
                        >
                            <input
                                type="radio"
                                name={name}
                                value={option.value}
                                checked={value === option.value}
                                onChange={() => onChange(option.value)}
                                className="size-5 shrink-0 accent-primary focus-visible:outline-none"
                            />
                            {option.label}
                        </label>
                    ))}
                </div>
            </fieldset>
        </div>
    );
}
