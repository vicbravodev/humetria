import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

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
                <textarea
                    id={id}
                    name={name}
                    defaultValue={value}
                    rows={4}
                    aria-invalid={error !== undefined}
                    aria-describedby={describedBy}
                    className={cn(
                        'w-full rounded-md border border-input bg-card px-4 py-3 text-base transition-[color,border-color,box-shadow] duration-150 ease-out hover:border-foreground/60',
                        'focus-visible:border-ring focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring',
                        'aria-invalid:border-destructive aria-invalid:shadow-[inset_0_0_0_1px_var(--destructive)]',
                    )}
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
            <InputError id={describedBy} role="alert" message={error} />
        </div>
    );
}
