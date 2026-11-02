import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

/** A part of the report HR writes: a paragraph, or one line for a name or a license. */
export default function ReportField({
    name,
    label,
    value,
    error,
    multiline = false,
    hint,
}: {
    name: string;
    label: string;
    value: string;
    error: string | undefined;
    multiline?: boolean;
    hint?: string;
}) {
    const id = `report-${name}`;
    const describedBy = `${id}-error`;

    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            {hint !== undefined && (
                <p className="text-sm text-muted-foreground">{hint}</p>
            )}
            {multiline ? (
                <Textarea
                    id={id}
                    name={name}
                    defaultValue={value}
                    rows={4}
                    aria-invalid={error !== undefined}
                    aria-describedby={describedBy}
                />
            ) : (
                <Input
                    id={id}
                    name={name}
                    defaultValue={value}
                    aria-invalid={error !== undefined}
                    aria-describedby={describedBy}
                />
            )}
            <InputError id={describedBy} message={error} />
        </div>
    );
}
