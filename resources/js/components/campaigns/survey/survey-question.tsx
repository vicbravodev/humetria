import { cn } from '@/lib/utils';
import type { SurveyOption } from '@/types';

/**
 * One question with its options as full-width 56 px rows (design system: SurveyQuestion). The chosen row gets a 2 px
 * primary border, the accent fill and weight 500: three signals, not color alone. Choosing never moves on.
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
        <fieldset className="flex flex-col gap-3">
            <legend className="mb-3 flex flex-col gap-1">
                {caption !== undefined && (
                    <span className="text-sm text-muted-foreground">
                        {caption}
                    </span>
                )}
                <span className="text-xl leading-[30px] font-medium">
                    {text}
                </span>
            </legend>
            {options.map((option) => (
                <label
                    key={option.value}
                    className={cn(
                        'flex min-h-14 cursor-pointer items-center gap-4 rounded-lg border bg-card px-4 text-lg leading-7 transition-[border-color,background-color] duration-150',
                        'has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-ring',
                        value === option.value
                            ? 'border-2 border-primary bg-accent px-[15px] font-medium'
                            : 'hover:border-foreground/40',
                    )}
                >
                    <input
                        type="radio"
                        name={name}
                        value={option.value}
                        checked={value === option.value}
                        onChange={() => onChange(option.value)}
                        className="size-6 shrink-0 accent-primary focus-visible:outline-none"
                    />
                    {option.label}
                </label>
            ))}
        </fieldset>
    );
}
