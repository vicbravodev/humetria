import { useHeldValue } from '@/hooks/use-held-value';
import { useIsMobile } from '@/hooks/use-mobile';
import { usePickerPopover } from '@/hooks/use-picker-popover';
import { useTypedField } from '@/hooks/use-typed-field';
import { formatDateText, maskDateText, parseDateText } from '@/lib/date-text';
import { textProblem } from '@/lib/text-problem';
import type { Voice } from '@/lib/text-problem';

export type DateFieldProps = {
    defaultValue?: string | undefined;
    /** The first day that can be picked, Y-m-d. */
    min?: string | undefined;
    /** The last day that can be picked, Y-m-d. */
    max?: string | undefined;
    /** The day picked, when the page holds it (Y-m-d); without it the field keeps its own. */
    value?: string | undefined;
    onChange?: ((value: string) => void) | undefined;
    /** A required day offers no «Borrar». */
    required?: boolean;
    voice?: Voice;
};

/** What a DatePicker keeps: the day, the text typed in the field (and in the phone's sheet) and its calendar. */
export function useDateField({
    defaultValue = '',
    min,
    max,
    value: held,
    onChange,
    required = false,
    voice = 'tu',
}: DateFieldProps) {
    const [value, setValue] = useHeldValue(defaultValue, held, onChange);
    const shape = {
        read: (text: string) => parseDateText(text, { min, max }),
        write: formatDateText,
        mask: maskDateText,
    };
    const field = useTypedField(value, setValue, shape);
    const sheetField = useTypedField(value, setValue, shape);
    const popover = usePickerPopover();

    return {
        value,
        field,
        sheetField,
        popover,
        mobile: useIsMobile(),
        problem:
            field.problem === undefined
                ? undefined
                : textProblem(field.problem, {
                      kind: 'date' as const,
                      limits: { min, max },
                      voice,
                  }),
        clear: required
            ? undefined
            : () => {
                  field.pick('');
                  popover.refocus();
              },
        pick: (day: string) => {
            field.pick(day);
            popover.close();
        },
    };
}
