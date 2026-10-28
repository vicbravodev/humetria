import type { ComponentProps } from 'react';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
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

    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            <div className="flex items-center gap-2">
                {prefix !== undefined && (
                    <span className="text-muted-foreground">{prefix}</span>
                )}
                <Input
                    {...input}
                    id={id}
                    name={name}
                    aria-invalid={error === undefined ? undefined : true}
                    aria-describedby={error === undefined ? undefined : errorId}
                />
            </div>
            <InputError id={errorId} role="alert" message={error} />
        </div>
    );
}
