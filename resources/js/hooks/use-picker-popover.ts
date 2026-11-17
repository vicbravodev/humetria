import { useRef, useState } from 'react';
import type { KeyboardEvent, MouseEvent } from 'react';
import { pickerFocusTarget } from '@/lib/calendar-date';

/**
 * The calendar under a picker's text field. A click in the field opens it and leaves the focus there to keep typing;
 * the calendar button, ArrowDown or Alt+ArrowDown take the focus into it. Escape or a pick close it and give the focus
 * back to the field; clicking or tabbing elsewhere closes it and leaves the focus where it went.
 */
export function usePickerPopover() {
    const [open, setOpen] = useState(false);
    const box = useRef<HTMLDivElement>(null);
    const text = useRef<HTMLInputElement>(null);
    const content = useRef<HTMLDivElement>(null);
    const intoCalendar = useRef(false);
    const backToField = useRef(false);

    // The field the person was in: the one clicked or typed in last, else the first of the box.
    const focusField = () =>
        (text.current ?? box.current?.querySelector('input'))?.focus();

    const focusCalendar = () => {
        const target =
            content.current?.querySelector<HTMLElement>(pickerFocusTarget);

        if (target === null || target === undefined) {
            intoCalendar.current = true;
            setOpen(true);
        } else {
            target.focus();
        }
    };

    return {
        open,
        setOpen,
        boxRef: box,
        textRef: text,
        content,
        /** Closes the calendar after a pick, with the focus back in the field. */
        close: () => {
            backToField.current = true;
            setOpen(false);
            focusField();
        },
        /** Gives the focus back to the field, after «Borrar» took it. */
        refocus: focusField,
        openFromField: (event: MouseEvent<HTMLInputElement>) => {
            text.current = event.currentTarget;
            setOpen(true);
        },
        /** The calendar button toggles it (it is the popover's trigger); opened from it, the focus goes in. */
        openFromButton: () => {
            intoCalendar.current = true;
        },
        fieldKeys: (event: KeyboardEvent<HTMLInputElement>) => {
            text.current = event.currentTarget;

            if (event.key === 'ArrowDown') {
                event.preventDefault();
                focusCalendar();
            }
        },
        contentProps: {
            ref: content,
            onOpenAutoFocus: (event: Event) => {
                event.preventDefault();

                if (
                    intoCalendar.current &&
                    event.currentTarget instanceof HTMLElement
                ) {
                    event.currentTarget
                        .querySelector<HTMLElement>(pickerFocusTarget)
                        ?.focus();
                }

                intoCalendar.current = false;
            },
            onEscapeKeyDown: () => {
                backToField.current = true;
            },
            onCloseAutoFocus: (event: Event) => {
                event.preventDefault();

                if (backToField.current) {
                    focusField();
                }

                backToField.current = false;
            },
            // The field and its buttons are outside the calendar: touching them keeps it open.
            onInteractOutside: (event: Event) => {
                if (
                    event.target instanceof Node &&
                    box.current?.contains(event.target)
                ) {
                    event.preventDefault();
                }
            },
        },
    };
}
