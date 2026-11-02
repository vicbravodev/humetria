import type { ComponentProps } from 'react';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Props = Omit<ComponentProps<'input'>, 'id' | 'name'> & {
    name: string;
    label: string;
    error?: string | undefined;
};

export default function DemoTextField({ name, label, error, ...input }: Props) {
    const id = `demo-${name}`;
    const errorId = `${id}-error`;

    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            <Input
                {...input}
                id={id}
                name={name}
                aria-invalid={error === undefined ? undefined : true}
                aria-describedby={error === undefined ? undefined : errorId}
            />
            <InputError id={errorId} message={error} />
        </div>
    );
}
