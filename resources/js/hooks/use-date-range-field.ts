import { useRef } from 'react';
import type { MouseEvent } from 'react';
import { useIsMobile } from '@/hooks/use-mobile';
import { usePickerPopover } from '@/hooks/use-picker-popover';
import { useTypedField } from '@/hooks/use-typed-field';
import { pickInRange } from '@/lib/date-range';
import type { DateRange } from '@/lib/date-range';
import { formatDateText, maskDateText, parseDateText } from '@/lib/date-text';
import type { TextLimits } from '@/lib/date-text';
import { textProblem } from '@/lib/text-problem';

export type DateRangeFieldProps = {
    value: DateRange;
    onChange: (range: DateRange) => void;
    /** The first day either end can be, Y-m-d. */
    min?: string | undefined;
    /** The last day either end can be, Y-m-d. */
    max?: string | undefined;
};

type End = 'from' | 'to';

/**
 * What a DateRangePicker keeps: the text typed in each end (taken when the field is left, so a filter asks the server
 * once), its calendar, and which end the next tap on a day sets. The start never goes after the end, nor the end before.
 */
export function useDateRangeField({
    value,
    onChange,
    min,
    max,
}: DateRangeFieldProps) {
    const popover = usePickerPopover();
    const mobile = useIsMobile();
    const next = useRef<End>('from');
    const limits: Record<End, TextLimits> = {
        from: { min, max: value.to === '' ? max : value.to },
        to: { min: value.from === '' ? min : value.from, max },
    };
    const shape = (key: End) => ({
        read: (text: string) => parseDateText(text, limits[key]),
        write: formatDateText,
        mask: maskDateText,
        onLeave: true,
    });
    const set = (key: End) => (day: string) =>
        onChange({ ...value, [key]: day });
    const fields = {
        from: useTypedField(value.from, set('from'), shape('from')),
        to: useTypedField(value.to, set('to'), shape('to')),
    };
    const problem = (key: End) => {
        const kind = fields[key].problem;

        return kind === undefined
            ? undefined
            : textProblem(kind, { kind: 'date', limits: limits[key] });
    };
    const whole = (range: DateRange) => {
        fields.from.reset();
        fields.to.reset();
        onChange(range);
    };

    return {
        ...fields,
        popover,
        mobile,
        problems: { from: problem('from'), to: problem('to') },
        /** What the text of one end takes: its id (`${id}-from`), its name, and the calendar it opens. */
        endProps: (key: End, id: string) => ({
            id: `${id}-${key}`,
            'aria-label': key === 'from' ? 'Desde' : 'Hasta',
            placeholder: 'dd/mm/aaaa',
            readOnly: mobile,
            invalid: problem(key) !== undefined,
            describedBy:
                problem(key) === undefined ? undefined : `${id}-${key}-problem`,
            onClick: (event: MouseEvent<HTMLInputElement>) => {
                // Opened from the second end, the next tap sets the end, when there is a start.
                next.current =
                    key === 'to' && value.from !== '' ? 'to' : 'from';
                popover.openFromField(event);
            },
            onKeyDown: popover.fieldKeys,
            className: 'w-0 px-3',
        }),
        openFromButton: () => {
            next.current = 'from';
            popover.openFromButton();
        },
        pick: (day: string) => {
            const picked = pickInRange(value, day, next.current);

            next.current = picked.next;
            whole(picked.range);

            if (picked.done) {
                popover.close();
            }
        },
        shortcut: (range: DateRange) => {
            next.current = 'from';
            whole(range);
            popover.close();
        },
    };
}
