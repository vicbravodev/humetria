import InputError from '@/components/humetria/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

/**
 * A text HR writes in the Programa. Free text may carry a name by mistake, so every one says what not to write (criterio
 * C-33: the Programa never points at a person).
 */
export default function TextField({
    name,
    label,
    defaultValue = '',
    error,
    multiline = false,
    hint,
}: {
    name: string;
    label: string;
    defaultValue?: string;
    error: string | undefined;
    multiline?: boolean;
    hint?: string;
}) {
    const id = `program-${name}`;
    const note = hint ?? 'No escribas nombres ni datos de personas.';

    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            {multiline ? (
                <Textarea
                    id={id}
                    name={name}
                    defaultValue={defaultValue}
                    rows={4}
                    aria-invalid={error !== undefined}
                    aria-describedby={`${id}-hint`}
                />
            ) : (
                <Input
                    id={id}
                    name={name}
                    defaultValue={defaultValue}
                    aria-invalid={error !== undefined}
                    aria-describedby={`${id}-hint`}
                />
            )}
            <p id={`${id}-hint`} className="text-sm text-muted-foreground">
                {note}
            </p>
            <InputError message={error} />
        </div>
    );
}
