import FormSection from '@/components/compensation/form-section';
import TenureTableField from '@/components/compensation/tenure-table-field';
import FormField from '@/components/form-field';
import DatePicker from '@/components/humetria/date-picker';
import InputError from '@/components/humetria/input-error';
import { Label } from '@/components/ui/label';
import { useYearStart } from '@/hooks/use-year-start';
import { tenureRowsOf } from '@/lib/compensation/benefits';
import type { BenefitRow } from '@/types';

/**
 * The new terms of a benefit. It starts from the latest ones, since a revision rarely changes everything, and the
 * vigencia is the start of this year; a vigencia that already has terms replaces them.
 */
export default function TermFormFields({
    errors,
    benefit,
}: {
    errors: Partial<Record<string, string>>;
    benefit: BenefitRow;
}) {
    const latest = benefit.terms[0];
    const start = useYearStart();

    return (
        <FormSection title="Términos nuevos">
            <div className="grid items-start gap-4 sm:grid-cols-2">
                <FormField
                    name="value"
                    idPrefix="term"
                    label={`Valor (${benefit.unit})`}
                    inputMode="decimal"
                    autoComplete="off"
                    defaultValue={latest?.value}
                    error={errors['value']}
                />
                <div className="grid gap-2">
                    <Label htmlFor="term-effective_from">Vigente desde</Label>
                    <DatePicker
                        id="term-effective_from"
                        name="effective_from"
                        defaultValue={start}
                        invalid={errors['effective_from'] !== undefined}
                        describedBy="term-effective_from-error"
                    />
                    <InputError
                        id="term-effective_from-error"
                        message={errors['effective_from']}
                    />
                </div>
            </div>
            <TenureTableField
                initial={tenureRowsOf(latest?.tenure_table ?? null)}
                unit={benefit.unit}
                error={errors['tenure_table']}
            />
        </FormSection>
    );
}
