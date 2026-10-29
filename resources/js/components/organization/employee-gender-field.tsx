import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { ChoiceOption } from '@/types';

export default function EmployeeGenderField({
    genders,
    defaultValue,
    error,
}: {
    genders: ChoiceOption[];
    defaultValue: string | undefined;
    error: string | undefined;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor="employee-gender">Género</Label>
            <Select name="gender" defaultValue={defaultValue}>
                <SelectTrigger
                    id="employee-gender"
                    className="w-full"
                    aria-invalid={error === undefined ? undefined : true}
                    aria-describedby={
                        error === undefined
                            ? undefined
                            : 'employee-gender-error'
                    }
                >
                    <SelectValue placeholder="Elige una opción" />
                </SelectTrigger>
                <SelectContent>
                    {genders.map((gender) => (
                        <SelectItem key={gender.value} value={gender.value}>
                            {gender.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <InputError
                id="employee-gender-error"
                role="alert"
                message={error}
            />
        </div>
    );
}
