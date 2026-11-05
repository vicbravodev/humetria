import DatePicker from '@/components/humetria/date-picker';
import InputError from '@/components/humetria/input-error';
import { Label } from '@/components/ui/label';
import { useYearStart } from '@/hooks/use-year-start';

/** The vigencia of a salary: it starts on the first day of this year unless another day is picked. */
export default function SalaryDateField({
    idPrefix,
    error,
}: {
    idPrefix: string;
    error: string | undefined;
}) {
    const start = useYearStart();
    const id = `${idPrefix}-effective_from`;

    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>Vigente desde</Label>
            <DatePicker
                id={id}
                name="effective_from"
                defaultValue={start}
                invalid={error !== undefined}
                describedBy={`${id}-error`}
            />
            <InputError id={`${id}-error`} message={error} />
        </div>
    );
}
