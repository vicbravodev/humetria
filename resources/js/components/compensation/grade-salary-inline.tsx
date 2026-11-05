import { Form } from '@inertiajs/react';
import {
    InputGroup,
    InputGroupInput,
    InputGroupText,
} from '@/components/ui/input-group';
import { gradeName } from '@/lib/compensation/salaries';
import { store } from '@/routes/tenant/compensation/tabulator';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import type { TabulatorRow } from '@/types';

/**
 * The problem of a category without a salary, where it is (board V4-Comp-Datos): its salary is captured in its row, from today,
 * and saved with Enter. Its history and a salary from another day are in «Historial».
 */
export default function GradeSalaryInline({
    grade,
    today,
}: {
    grade: TabulatorRow;
    today: string;
}) {
    const tenantSlug = useTenantSlug();

    return (
        <Form
            {...store.form(tenantSlug)}
            options={{ preserveScroll: true }}
            className="max-w-[220px]"
        >
            {({ errors }) => (
                <>
                    <input
                        type="hidden"
                        name="category"
                        value={grade.category}
                    />
                    <input type="hidden" name="level" value={grade.level} />
                    <input type="hidden" name="effective_from" value={today} />
                    <InputGroup className="border-destructive">
                        <InputGroupText>$</InputGroupText>
                        <InputGroupInput
                            name="daily_salary"
                            inputMode="decimal"
                            autoComplete="off"
                            placeholder="Captura el salario"
                            aria-label={`Salario diario de ${gradeName(grade)}, desde hoy`}
                            aria-invalid
                            aria-describedby={
                                errors['daily_salary'] === undefined
                                    ? undefined
                                    : `grade-${grade.id}-error`
                            }
                        />
                    </InputGroup>
                    {errors['daily_salary'] !== undefined && (
                        <p
                            id={`grade-${grade.id}-error`}
                            role="alert"
                            className="mt-1 text-sm text-destructive"
                        >
                            {errors['daily_salary']}
                        </p>
                    )}
                </>
            )}
        </Form>
    );
}
