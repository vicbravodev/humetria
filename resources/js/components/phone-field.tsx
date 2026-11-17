import InputError from '@/components/humetria/input-error';
import PhoneInput from '@/components/humetria/phone-input';
import { Label } from '@/components/ui/label';

/** FormField for a phone: a labelled PhoneInput with its error under it, announced to screen readers. */
export default function PhoneField({
    name,
    label,
    idPrefix = 'field',
    error,
    ...input
}: {
    name: string;
    label: string;
    /** The id is `${idPrefix}-${name}`, so two forms on a page never share one. */
    idPrefix?: string;
    error?: string | undefined;
    defaultValue?: string | null | undefined;
    required?: boolean;
    autoComplete?: string;
    className?: string;
}) {
    const id = `${idPrefix}-${name}`;
    const errorId = `${id}-error`;

    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            <PhoneInput
                {...input}
                id={id}
                name={name}
                invalid={error !== undefined}
                describedBy={error === undefined ? undefined : errorId}
            />
            <InputError id={errorId} message={error} />
        </div>
    );
}
