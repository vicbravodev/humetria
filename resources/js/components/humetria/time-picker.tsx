import { Clock } from 'lucide-react';
import { InputGroup } from '@/components/ui/input-group';
import { PickerProblem, PickerText } from '@/components/ui/picker';
import { useTimeField } from '@/hooks/use-time-field';
import type { TimeFieldProps } from '@/hooks/use-time-field';
import { cn } from '@/lib/utils';

type Props = TimeFieldProps & {
    id: string;
    /**
     * The form field, on the text itself: leaving the field (or Enter) writes it as HH:mm before the form is sent.
     * Without it the page sends the time its own way.
     */
    name?: string;
    invalid?: boolean;
    describedBy?: string | undefined;
    required?: boolean;
    className?: string;
};

/**
 * A time of day on a 24-hour clock, typed: "7:30", "730", "7", "7:30 pm"… all come out as HH:mm when the field is
 * left. The arrow keys move it a quarter of an hour. On a phone it brings up the number keyboard.
 */
export default function TimePicker({
    id,
    name,
    invalid = false,
    describedBy,
    required = false,
    className,
    ...props
}: Props) {
    const { field, problem, keys } = useTimeField(props);
    const problemId = `${id}-problem`;

    return (
        <div
            data-slot="time-picker"
            className={cn('grid min-w-0 gap-1.5', className)}
        >
            <InputGroup>
                <Clock
                    aria-hidden
                    className="ml-3 size-4 shrink-0 text-muted-foreground"
                />
                <PickerText
                    id={id}
                    {...(name === undefined ? {} : { name })}
                    field={field}
                    placeholder="hh:mm"
                    inputMode="numeric"
                    required={required}
                    invalid={invalid || problem !== undefined}
                    describedBy={[describedBy, problem && problemId]
                        .filter(Boolean)
                        .join(' ')}
                    onKeyDown={keys}
                    className="pl-2"
                />
            </InputGroup>
            <PickerProblem id={problemId} message={problem} />
        </div>
    );
}
