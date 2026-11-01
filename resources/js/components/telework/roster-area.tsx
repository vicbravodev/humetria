import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';

const AREA =
    'min-h-24 w-full rounded-md border border-input bg-card px-4 py-3 text-base focus-visible:border-ring focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring aria-invalid:border-destructive';

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
            <textarea
                id={`roster-${name}`}
                name={name}
                className={AREA}
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
