import { CalendarRange } from 'lucide-react';
import { InputGroup } from '@/components/ui/input-group';
import {
    MonthGrid,
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
import { useMonthField } from '@/hooks/use-month-field';
import type { MonthFieldProps } from '@/hooks/use-month-field';

type Props = MonthFieldProps & {
    id: string;
    /** The form field: it submits the month as Y-m, or empty. */
    name: string;
    invalid?: boolean;
    describedBy?: string | undefined;
};

/**
 * A month typed as mm/aaaa ("12/2026", "122026", "dic 2026", "2026-12") or picked in the grid of a year. It always
 * submits Y-m. On a phone the grid comes up as a sheet from the bottom.
 */
export default function MonthPicker({
    id,
    name,
    invalid = false,
    describedBy,
    ...props
}: Props) {
    const { value, field, popover, mobile, problem, pick } =
        useMonthField(props);
    const { boxRef, textRef } = popover;
    const problemId = `${id}-problem`;
    const grid = (
        <MonthGrid
            value={value}
            min={props.min}
            max={props.max}
            onPick={pick}
        />
    );

    return (
        <div data-slot="month-picker" className="grid min-w-0 gap-1.5">
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
                        placeholder="mm/aaaa"
                        readOnly={mobile}
                        invalid={invalid || problem !== undefined}
                        describedBy={[describedBy, problem && problemId]
                            .filter(Boolean)
                            .join(' ')}
                        onClick={popover.openFromField}
                        onKeyDown={popover.fieldKeys}
                    />
                    <PopoverTrigger asChild>
                        <PickerButton
                            label="Abrir los meses"
                            icon={
                                <CalendarRange aria-hidden className="size-5" />
                            }
                            onClick={popover.openFromButton}
                        />
                    </PopoverTrigger>
                </InputGroup>
                <PopoverContent
                    align="end"
                    aria-label="Elige un mes"
                    {...popover.contentProps}
                >
                    {grid}
                </PopoverContent>
            </Popover>
            <PickerProblem id={problemId} message={problem} />
            {mobile && (
                <PickerSheet
                    open={popover.open}
                    onOpenChange={popover.setOpen}
                    title="Elige un mes"
                    hint="Toca el mes; las flechas cambian de año."
                >
                    {grid}
                </PickerSheet>
            )}
        </div>
    );
}
