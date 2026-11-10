import InputError from '@/components/humetria/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

/** A numeric field with its label, its hint and its error. */
export default function PolicyNumberField({
    name,
    label,
    hint,
    min,
    defaultValue,
    error,
}: {
    name: string;
    label: string;
    hint: string;
    min: number;
    defaultValue: number | null | undefined;
    error: string | undefined;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={`policy-${name}`}>{label}</Label>
            <Input
                id={`policy-${name}`}
                name={name}
                type="number"
                inputMode="numeric"
                min={min}
                defaultValue={defaultValue ?? ''}
                aria-invalid={error !== undefined || undefined}
            />
            <p className="text-caption text-muted-foreground">{hint}</p>
            <InputError message={error} />
        </div>
    );
}
