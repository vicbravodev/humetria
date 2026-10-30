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
}: {
    name: string;
    caption?: string;
    text: string;
    options: SurveyOption[];
    value: string | undefined;
    onChange: (value: string) => void;
}) {
    return (
        // The separator sits on a wrapper: on the fieldset itself the legend would cut it.
        <div className="md:py-6 md:[&+&]:border-t">
            <fieldset className="flex flex-col gap-2.5 md:gap-3.5">
                <legend className="mb-3.5 flex flex-col gap-2">
                    {caption !== undefined && (
                        <span className="text-base text-muted-foreground">
                            {caption}
                        </span>
                    )}
                    <span className="text-xl leading-[30px] font-medium tracking-[-0.005em]">
                        {text}
                    </span>
                </legend>
                <div className="flex flex-col gap-2.5 md:flex-row md:gap-2">
                    {options.map((option) => (
                        <label
                            key={option.value}
                            className={cn(
                                'flex min-h-14 cursor-pointer items-center gap-3 rounded-lg border border-input bg-card px-4 py-3 text-lg leading-7 transition-[border-color,background-color] duration-150 hover:bg-muted',
                                'md:flex-1 md:basis-0 md:flex-col md:justify-center md:gap-2 md:px-2 md:py-3.5 md:text-center md:text-base md:leading-[22px]',
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
