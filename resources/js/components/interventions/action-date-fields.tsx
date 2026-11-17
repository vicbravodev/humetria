import { useState } from 'react';
import DatePicker from '@/components/humetria/date-picker';
import InputError from '@/components/humetria/input-error';
import { Label } from '@/components/ui/label';

type Errors = Partial<Record<string, string>>;

/**
 * The dates of an action (num. 8.4 c): an optional start and the due day. They share a row only where the written day fits
 * in each (by the width of the form, not of the window: the side panel stacks them), and the due day cannot be picked
 * before the start.
 */
export default function ActionDateFields({
    startsOn,
    dueOn,
    errors,
    onEdited,
}: {
    startsOn: string | undefined;
    dueOn: string | undefined;
    errors: Errors;
    onEdited: (field: string) => void;
}) {
    const [start, setStart] = useState(startsOn);

    return (
        <div className="@container">
            <div className="grid gap-4 @lg:grid-cols-2">
                <div className="grid min-w-0 gap-2">
                    <Label htmlFor="action-starts">Inicio (opcional)</Label>
                    <DatePicker
                        id="action-starts"
                        name="starts_on"
                        defaultValue={startsOn}
                        invalid={errors['starts_on'] !== undefined}
                        onChange={(day) => {
                            setStart(day);
                            onEdited('starts_on');
                        }}
                    />
                    <InputError message={errors['starts_on']} />
                </div>
                <div className="grid min-w-0 gap-2">
                    <Label htmlFor="action-due">Fecha límite</Label>
                    <DatePicker
                        id="action-due"
                        name="due_on"
                        defaultValue={dueOn}
                        min={start}
                        invalid={errors['due_on'] !== undefined}
                        onChange={() => onEdited('due_on')}
                    />
                    <InputError message={errors['due_on']} />
                </div>
            </div>
        </div>
    );
}
