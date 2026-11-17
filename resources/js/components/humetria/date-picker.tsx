import { CalendarDays, X } from 'lucide-react';
import { InputGroup } from '@/components/ui/input-group';
import {
    DayCalendar,
    PickerButton,
    PickerProblem,
    PickerSheet,
    PickerText,
} from '@/components/ui/picker';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { useDateField } from '@/hooks/use-date-field';
import { useFallbackLabel } from '@/hooks/use-fallback-label';
import type { DateFieldProps } from '@/hooks/use-date-field';

type Props = DateFieldProps & {
    id: string;
    /** The form field: it submits the day as Y-m-d, or empty. */
    name: string;
    invalid?: boolean;
    describedBy?: string | undefined;
    /** Names the calendar; the portal, which speaks in usted, passes its own. */
    prompt?: string;
};

/**
 * A day typed as dd/mm/aaaa (or pasted in any usual Mexican way: "7 oct 2018", "7-10-18", "2018-10-07") or picked in a
 * calendar in Spanish, with menus for the month and year. It always submits Y-m-d. On a phone the calendar comes up as a
 * sheet from the bottom, where the day can also be typed.
 */
export default function DatePicker({
    id,
    name,
    invalid = false,
    describedBy,
    prompt = 'Elige una fecha',
    ...props
}: Props) {
    const { value, field, sheetField, popover, mobile, problem, clear, pick } =
        useDateField(props);
    const { boxRef, textRef } = popover;
    useFallbackLabel(textRef, prompt);
    const problemId = `${id}-problem`;
    const calendar = (
        <DayCalendar
            value={value}
            min={props.min}
            max={props.max}
            onPick={pick}
        />
    );

    return (
        <div data-slot="date-picker" className="grid min-w-0 gap-1.5">
            <input type="hidden" name={name} value={value} />
            <Popover
                open={popover.open && !mobile}
                onOpenChange={popover.setOpen}
            >
                <InputGroup ref={boxRef}>
                    <PickerText
                        id={id}
                        ref={textRef}
                        field={field}
                        placeholder="dd/mm/aaaa"
                        readOnly={mobile}
                        invalid={invalid || problem !== undefined}
                        describedBy={[describedBy, problem && problemId]
                            .filter(Boolean)
                            .join(' ')}
                        onClick={popover.openFromField}
                        onKeyDown={popover.fieldKeys}
                    />
                    {clear !== undefined && value !== '' && (
                        <PickerButton
                            label="Borrar la fecha"
                            icon={<X aria-hidden className="size-4" />}
                            onClick={clear}
                        />
                    )}
                    <PopoverTrigger asChild>
                        <PickerButton
                            label="Abrir el calendario"
                            icon={
                                <CalendarDays aria-hidden className="size-5" />
                            }
                            onClick={popover.openFromButton}
                        />
                    </PopoverTrigger>
                </InputGroup>
                <PopoverContent
                    align="end"
                    aria-label={prompt}
                    {...popover.contentProps}
                >
                    {calendar}
                </PopoverContent>
            </Popover>
            <PickerProblem id={problemId} message={problem} />
            {mobile && (
                <PickerSheet
                    open={popover.open}
                    onOpenChange={popover.setOpen}
                    title={prompt}
                    hint="Escribe la fecha o tócala en el calendario."
                    onClear={clear}
                >
                    <PickerText
                        id={`${id}-typed`}
                        field={sheetField}
                        placeholder="dd/mm/aaaa"
                        label="Fecha"
                    />
                    {calendar}
                </PickerSheet>
            )}
        </div>
    );
}
