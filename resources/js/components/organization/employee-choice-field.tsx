import Combobox from '@/components/humetria/combobox';
import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import type { ChoiceOption } from '@/types';

/** A field chosen by searching a list (a work center, an area); `allowNew` lets the person add what they typed. */
export default function EmployeeChoiceField({
    name,
    label,
    options,
    defaultValue,
    error,
    allowNew = false,
}: {
    name: string;
    label: string;
    options: ChoiceOption[];
    defaultValue: ChoiceOption | undefined;
    error: string | undefined;
    allowNew?: boolean;
}) {
    const id = `employee-${name}`;

    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            <Combobox
                id={id}
                name={name}
                source={{ options, allowNew }}
                defaultValue={defaultValue}
                placeholder={allowNew ? 'Elige o escribe una' : 'Elige uno'}
                invalid={error !== undefined}
                describedBy={error === undefined ? undefined : `${id}-error`}
            />
            <InputError id={`${id}-error`} role="alert" message={error} />
        </div>
    );
}
