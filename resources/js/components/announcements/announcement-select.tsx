import InputError from '@/components/humetria/input-error';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { AnnouncementOption } from '@/types';

/** One choice of the announcement's form as a list that opens (tablero Avisos: Tipo, Destino). */
export default function AnnouncementSelect({
    id,
    label,
    field,
    error,
}: {
    id: string;
    label: string;
    field: {
        name?: string;
        options: AnnouncementOption[];
        value: string;
        onChange: (value: string) => void;
    };
    error: string | undefined;
}) {
    return (
        <div className="grid content-start gap-2">
            <Label htmlFor={id}>{label}</Label>
            <Select
                name={field.name}
                value={field.value}
                onValueChange={field.onChange}
            >
                <SelectTrigger
                    id={id}
                    aria-invalid={error !== undefined}
                    className="w-full"
                >
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {field.options.map((option) => (
                        <SelectItem key={option.value} value={option.value}>
                            {option.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <InputError message={error} />
        </div>
    );
}
