import { CalendarDays } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { InputGroup } from '@/components/ui/input-group';
import {
    PickerButton,
    PickerProblem,
    PickerSheet,
    PickerText,
    RangeCalendar,
} from '@/components/ui/picker';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { useDateRangeField } from '@/hooks/use-date-range-field';
import type { DateRangeFieldProps } from '@/hooks/use-date-range-field';
import type { RangeShortcut } from '@/lib/date-range';
import { cn } from '@/lib/utils';

type Props = DateRangeFieldProps & {
    /** The ends are `${id}-from` and `${id}-to`. */
    id: string;
    label: string;
    /** Periods one tap away, beside the calendar. */
    shortcuts?: RangeShortcut[];
    /** The form fields, when the period is sent in a form: each end submits Y-m-d, or empty. */
    names?: { from: string; to: string };
    className?: string;
};

/**
 * A period of days in one field: its first and last day typed as dd/mm/aaaa (taken when the field is left) or picked
 * with two taps in one calendar, or from a shortcut. On a phone the calendar comes up as a sheet from the bottom.
 */
export default function DateRangePicker({
    id,
    label,
    shortcuts = [],
    names,
    className,
    ...props
}: Props) {
    const range = useDateRangeField(props);
    const { from, to, popover, mobile, problems } = range;
    const { boxRef } = popover;
    const { value } = props;
    const quick = shortcuts.map((shortcut) => (
        <Button
            key={shortcut.label}
            type="button"
            variant="ghost"
            className="justify-start"
            onClick={() => range.shortcut(shortcut)}
        >
            {shortcut.label}
        </Button>
    ));
    const calendar = (
        <RangeCalendar
            {...value}
            min={props.min}
            max={props.max}
            onPick={range.pick}
        />
    );

    return (
        <fieldset
            data-slot="date-range-picker"
            className={cn('grid w-full min-w-0 gap-1.5 sm:w-80', className)}
        >
            <legend className="mb-1.5 text-sm leading-none font-medium">
                {label}
            </legend>
            {names !== undefined && (
                <>
                    <input type="hidden" name={names.from} value={value.from} />
                    <input type="hidden" name={names.to} value={value.to} />
                </>
            )}
            <Popover
                open={popover.open && !mobile}
                onOpenChange={popover.setOpen}
            >
                <InputGroup ref={boxRef}>
                    <PickerText field={from} {...range.endProps('from', id)} />
                    <span aria-hidden className="text-muted-foreground">
                        –
                    </span>
                    <PickerText field={to} {...range.endProps('to', id)} />
                    <PopoverTrigger asChild>
                        <PickerButton
                            label="Abrir el calendario"
                            icon={
                                <CalendarDays aria-hidden className="size-5" />
                            }
                            onClick={range.openFromButton}
                        />
                    </PopoverTrigger>
                </InputGroup>
                <PopoverContent
                    align="end"
                    aria-label={label}
                    {...popover.contentProps}
                >
                    <div className="flex flex-col gap-3 sm:flex-row">
                        {quick.length > 0 && (
                            <div className="flex flex-col gap-1">{quick}</div>
                        )}
                        {calendar}
                    </div>
                </PopoverContent>
            </Popover>
            <PickerProblem id={`${id}-from-problem`} message={problems.from} />
            <PickerProblem id={`${id}-to-problem`} message={problems.to} />
            {mobile && (
                <PickerSheet
                    open={popover.open}
                    onOpenChange={popover.setOpen}
                    title={label}
                    hint="Toca el primer y el último día, o elige un atajo."
                >
                    {quick.length > 0 && (
                        <div className="flex flex-wrap justify-center gap-2">
                            {quick}
                        </div>
                    )}
                    {calendar}
                </PickerSheet>
            )}
        </fieldset>
    );
}
