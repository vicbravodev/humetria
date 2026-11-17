import { useHeldValue } from '@/hooks/use-held-value';
import { useIsMobile } from '@/hooks/use-mobile';
import { usePickerPopover } from '@/hooks/use-picker-popover';
import { useTypedField } from '@/hooks/use-typed-field';
import {
    formatMonthText,
    maskMonthText,
    parseMonthText,
} from '@/lib/date-text';
import { textProblem } from '@/lib/text-problem';

export type MonthFieldProps = {
    defaultValue?: string | undefined;
    /** The first month that can be picked, Y-m. */
    min?: string | undefined;
    /** The last month that can be picked, Y-m. */
    max?: string | undefined;
    /** The month, when the page holds it (Y-m); without it the field keeps its own. */
    value?: string | undefined;
    onChange?: ((value: string) => void) | undefined;
};

/** What a MonthPicker keeps: the month, the text typed in the field and the grid of months under it. */
export function useMonthField({
    defaultValue = '',
    min,
    max,
    value: held,
    onChange,
}: MonthFieldProps) {
    const [value, setValue] = useHeldValue(defaultValue, held, onChange);
    const field = useTypedField(value, setValue, {
        read: (text: string) => parseMonthText(text, { min, max }),
        write: formatMonthText,
        mask: maskMonthText,
    });
    const popover = usePickerPopover();

    return {
        value,
        field,
        popover,
        mobile: useIsMobile(),
        problem:
            field.problem === undefined
                ? undefined
                : textProblem(field.problem, {
                      kind: 'month',
                      limits: { min, max },
                  }),
        pick: (month: string) => {
            field.pick(month);
            popover.close();
        },
    };
}
