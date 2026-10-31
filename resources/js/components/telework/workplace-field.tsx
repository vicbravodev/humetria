import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { workplaceField } from '@/lib/workplace-field';

/** One text field of a workplace's address, named for the list it belongs to. */
export default function WorkplaceField({
    index,
    field,
    label,
    errors,
    ...input
}: {
    index: number | null;
    field: string;
    label: string;
    errors: Partial<Record<string, string>>;
    required?: boolean;
    maxLength: number;
    inputMode?: 'numeric';
}) {
    const place = workplaceField(index, field);
    const id = `workplace-${index ?? 'new'}-${field}`;
    const error = errors[place.error];

    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            <Input
                id={id}
                name={place.name}
                aria-invalid={error === undefined ? undefined : true}
                aria-describedby={
                    error === undefined ? undefined : `${id}-error`
                }
                {...input}
            />
            <InputError id={`${id}-error`} role="alert" message={error} />
        </div>
    );
}
