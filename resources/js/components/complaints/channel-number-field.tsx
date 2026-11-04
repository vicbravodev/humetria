import InputError from '@/components/humetria/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Bound } from '@/types';

/** A whole number with its limits stated beside the label. */
export default function ChannelNumberField({
    name,
    label,
    value,
    bound,
    error,
}: {
    name: string;
    label: string;
    value: number;
    bound: Bound;
    error: string | undefined;
}) {
    return (
        <div className="grid content-start gap-2">
            <Label htmlFor={name}>{label}</Label>
            <Input
                id={name}
                name={name}
                type="number"
                inputMode="numeric"
                min={bound.min}
                max={bound.max}
                defaultValue={value}
                aria-invalid={error === undefined ? undefined : true}
            />
            <p className="text-caption text-muted-foreground">
                De {bound.min} a {bound.max}.
            </p>
            <InputError message={error} />
        </div>
    );
}
