import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

type Errors = Partial<Record<string, string>>;

/** A labelled text area of the roster form, with its hint and error. */
export default function RosterArea({
    name,
    label,
    hint,
    errors,
}: {
    name: string;
    label: string;
    hint?: string;
    errors: Errors;
}) {
    const error = errors[name];

    return (
        <div className="grid gap-2">
            <Label htmlFor={`roster-${name}`}>{label}</Label>
            <Textarea
                id={`roster-${name}`}
                name={name}
                aria-invalid={error === undefined ? undefined : true}
                aria-describedby={`roster-${name}-error`}
            />
            {hint !== undefined && (
                <p className="text-[14px] text-muted-foreground">{hint}</p>
            )}
            <InputError
                id={`roster-${name}-error`}
                role="alert"
                message={error}
            />
        </div>
    );
}
