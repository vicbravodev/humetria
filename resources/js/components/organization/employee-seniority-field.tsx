import DatePicker from '@/components/humetria/date-picker';
import InputError from '@/components/humetria/input-error';
import { Label } from '@/components/ui/label';
import { useIsUnion } from '@/hooks/use-is-union';
import { useToday } from '@/hooks/use-today';

/** The day a person's seniority counts from, when it is not the day they joined. A union's roster does not carry it. */
export default function EmployeeSeniorityField({
    error,
    defaultValue,
}: {
    error: string | undefined;
    defaultValue: string | undefined;
}) {
    const union = useIsUnion();
    const today = useToday();

    if (union) {
        return null;
    }

    return (
        <div className="grid gap-2">
            <Label htmlFor="employee-seniority_on">Antigüedad reconocida</Label>
            <DatePicker
                id="employee-seniority_on"
                name="seniority_on"
                defaultValue={defaultValue ?? ''}
                max={today}
                invalid={error !== undefined}
                describedBy="employee-seniority_on-error employee-seniority_on-hint"
            />
            <InputError id="employee-seniority_on-error" message={error} />
            <p
                id="employee-seniority_on-hint"
                className="text-caption text-muted-foreground"
            >
                Solo si es distinta de la fecha de ingreso, por ejemplo por
                sustitución patronal.
            </p>
        </div>
    );
}
