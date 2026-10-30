import { CalendarDays } from 'lucide-react';
import { useState } from 'react';
import { Calendar } from '@/components/ui/calendar';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { useHeldValue } from '@/hooks/use-held-value';
import {
    describeDate,
    fromCalendarDate,
    toCalendarDate,
} from '@/lib/calendar-date';
import { cn } from '@/lib/utils';

type Props = {
    id: string;
    /** The form field: it submits the picked day as Y-m-d. */
    name: string;
    defaultValue?: string | undefined;
    /** The first day that can be picked, Y-m-d. */
    min?: string | undefined;
    invalid?: boolean;
    describedBy?: string | undefined;
    /** The day picked, when the page holds it (Y-m-d); without it the field keeps its own. */
    value?: string | undefined;
    onChange?: ((value: string) => void) | undefined;
};

/**
 * A field that looks like Input and opens a calendar in Spanish under it. The day shows written out
 * ("30 de noviembre de 2026"); picking one closes the calendar and gives focus back to the field.
 */
export default function DatePicker({
    id,
    name,
    defaultValue = '',
    min,
    invalid = false,
    describedBy,
    value: held,
    onChange,
}: Props) {
    const [value, setValue] = useHeldValue(defaultValue, held, onChange);
    const [open, setOpen] = useState(false);
    const selected = fromCalendarDate(value);
    const first = min === undefined ? undefined : fromCalendarDate(min);
    const written = describeDate(value);
    const month = selected ?? first;

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <input type="hidden" name={name} value={value} />
            <PopoverTrigger
                id={id}
                data-slot="date-picker"
                aria-invalid={invalid || undefined}
                aria-describedby={describedBy}
                className={cn(
                    'flex h-11 w-full items-center gap-3 rounded-md border border-input bg-card px-4 text-left text-base transition-[color,border-color] duration-150 ease-out hover:border-foreground/60',
                    'focus-visible:border-ring focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring data-[state=open]:border-ring',
                    'aria-invalid:border-destructive aria-invalid:shadow-[inset_0_0_0_1px_var(--destructive)]',
                )}
            >
                <CalendarDays className="size-5 shrink-0 text-muted-foreground" />
                <span
                    className={cn(
                        written === undefined && 'text-muted-foreground',
                    )}
                >
                    {written ?? 'Elige una fecha'}
                </span>
            </PopoverTrigger>
            <PopoverContent
                aria-label="Elige una fecha"
                // Focus lands on the day the calendar offers to the keyboard: the chosen one, today or the first allowed.
                onOpenAutoFocus={(event) => {
                    event.preventDefault();

                    if (event.currentTarget instanceof HTMLElement) {
                        event.currentTarget
                            .querySelector<HTMLElement>(
                                '[data-day] button[tabindex="0"]',
                            )
                            ?.focus();
                    }
                }}
            >
                <Calendar
                    mode="single"
                    required
                    selected={selected}
                    {...(month === undefined ? {} : { defaultMonth: month })}
                    {...(first === undefined
                        ? {}
                        : { startMonth: first, disabled: { before: first } })}
                    onSelect={(day) => {
                        setValue(toCalendarDate(day));
                        setOpen(false);
                    }}
                />
            </PopoverContent>
        </Popover>
    );
}
