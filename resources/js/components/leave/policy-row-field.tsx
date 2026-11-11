import InputError from '@/components/humetria/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

/** One field of a row: its label, a number or text input and the error of that field. */
export default function RowField({
    id,
    label,
    name,
    value,
    error,
    onChange,
    type = 'number',
}: {
    id: string;
    label: string;
    name: string;
    value: string;
    error: string | undefined;
    onChange: (value: string) => void;
    type?: 'number' | 'text';
}) {
    return (
        <div className="grid min-w-0 gap-1.5">
            <Label htmlFor={id}>{label}</Label>
            <Input
                id={id}
                name={name}
                type={type}
                inputMode={type === 'number' ? 'numeric' : undefined}
                min={type === 'number' ? 1 : undefined}
                value={value}
                onChange={(event) => onChange(event.target.value)}
                aria-invalid={error !== undefined || undefined}
            />
            <InputError message={error} />
        </div>
    );
}
