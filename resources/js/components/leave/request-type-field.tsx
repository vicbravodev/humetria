import InputError from '@/components/humetria/input-error';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { LeaveTypeOption } from '@/types';

/** What the person asks for: vacations, or one of the leaves of the company, which use no vacation days. */
export default function RequestTypeField({
    types,
    value,
    onChange,
    error,
}: {
    types: LeaveTypeOption[];
    /** The id of the leave type; empty for vacations. */
    value: string;
    onChange: (typeId: string) => void;
    error: string | undefined;
}) {
    if (types.length === 0) {
        return null;
    }

    return (
        <div className="grid gap-2">
            <Label htmlFor="leave-type">¿Qué necesita?</Label>
            <input type="hidden" name="leave_type_id" value={value} />
            <Select
                value={value === '' ? 'vacation' : value}
                onValueChange={(next) =>
                    onChange(next === 'vacation' ? '' : next)
                }
            >
                <SelectTrigger id="leave-type" className="w-full">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="vacation">Vacaciones</SelectItem>
                    {types.map((type) => (
                        <SelectItem key={type.id} value={type.id}>
                            {type.name}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <InputError message={error} />
        </div>
    );
}
