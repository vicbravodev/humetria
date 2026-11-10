import FormField from '@/components/form-field';
import { useIsUnion } from '@/hooks/use-is-union';

/** The day a person's seniority counts from, when it is not the day they joined. A union's roster does not carry it. */
export default function EmployeeSeniorityField({
    error,
    defaultValue,
}: {
    error: string | undefined;
    defaultValue: string | undefined;
}) {
    const union = useIsUnion();

    if (union) {
        return null;
    }

    return (
        <div className="grid gap-2">
            <FormField
                name="seniority_on"
                idPrefix="employee"
                label="Antigüedad reconocida"
                type="date"
                defaultValue={defaultValue}
                error={error}
            />
            <p className="text-caption text-muted-foreground">
                Solo si es distinta de la fecha de ingreso, por ejemplo por
                sustitución patronal.
            </p>
        </div>
    );
}
