import type { ComponentProps } from 'react';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import {
    InputGroup,
    InputGroupInput,
    InputGroupText,
} from '@/components/ui/input-group';
import { Label } from '@/components/ui/label';

type Props = Omit<ComponentProps<'input'>, 'id' | 'name'> & {
    name: string;
    label: string;
    /** Text shown before the input, like the /admin/ of an address. */
    prefix?: string;
    error?: string | undefined;
};

export default function TenantField({
    name,
    label,
    prefix,
    error,
    ...input
}: Props) {
    const id = `tenant-${name}`;
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
