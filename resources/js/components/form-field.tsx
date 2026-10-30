import type { ComponentProps } from 'react';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import {
    InputGroup,
    InputGroupInput,
    InputGroupText,
} from '@/components/ui/input-group';
import { Label } from '@/components/ui/label';

export type FormFieldProps = Omit<ComponentProps<'input'>, 'id' | 'name'> & {
    name: string;
    label: string;
    /** The id is `${idPrefix}-${name}`, so two forms on a page never share one. */
    idPrefix?: string;
    /** Text shown before the input, like the /admin/ of an address. */
    prefix?: string;
    error?: string | undefined;
};

/** A labelled text field with its error under it, announced to screen readers. */
export default function FormField({
    name,
    label,
    idPrefix = 'field',
    prefix,
    error,
    ...input
}: FormFieldProps) {
    const id = `${idPrefix}-${name}`;
    const errorId = `${id}-error`;
    const control = {
        ...input,
        id,
        name,
        'aria-invalid': error === undefined ? undefined : true,
        'aria-describedby': error === undefined ? undefined : errorId,
    };

    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            {prefix === undefined ? (
                <Input {...control} />
            ) : (
                // The prefix sits inside the box, as part of the address being typed.
                <InputGroup>
                    <InputGroupText>{prefix}</InputGroupText>
                    <InputGroupInput {...control} />
                </InputGroup>
            )}
            <InputError id={errorId} role="alert" message={error} />
        </div>
    );
}
