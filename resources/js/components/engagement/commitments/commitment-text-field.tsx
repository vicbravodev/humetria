import InputError from '@/components/humetria/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

/** One text of a commitment: a line, or a few when it is a sentence people wrote. */
export default function CommitmentTextField({
    id,
    name,
    label,
    defaultValue,
    maxLength,
    error,
    multiline = false,
}: {
    id: string;
    name: string;
    label: string;
    defaultValue: string;
    maxLength: number;
    error: string | undefined;
    multiline?: boolean;
}) {
    const shared = {
        id,
        name,
        maxLength,
        defaultValue,
        'aria-invalid': error === undefined ? undefined : true,
    };

    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            {multiline ? (
                <Textarea rows={3} {...shared} />
            ) : (
                <Input {...shared} />
            )}
            <InputError message={error} />
        </div>
    );
}
