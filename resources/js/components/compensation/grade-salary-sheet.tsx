import type { ReactNode } from 'react';
import FormSection from '@/components/compensation/form-section';
import CompensationSheet from '@/components/compensation/compensation-sheet';
import GradeSalaryFields from '@/components/compensation/grade-salary-fields';
import SalaryHistory from '@/components/compensation/salary-history';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { gradeName } from '@/lib/compensation/salaries';
import { destroy, store } from '@/routes/tenant/compensation/tabulator';
import type { TabulatorRow } from '@/types';

/**
 * The salary of a category and level by vigencia. Opened from a row it shows what that category has been paid and asks
 * for the next salary; opened from «Nuevo salario» (no row) it also asks which category and level, which are created
 * when the company has none like them.
 */
export default function GradeSalarySheet({
    grade,
    open,
    onOpenChange,
    trigger,
}: {
    grade?: TabulatorRow;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    trigger?: ReactNode;
}) {
    const tenantSlug = useTenantSlug();

    return (
        <CompensationSheet
            open={open}
            onOpenChange={onOpenChange}
            copy={{
                title:
                    grade === undefined
                        ? 'Nuevo salario'
                        : `Salario de ${gradeName(grade)}`,
                description:
                    'Cada vigencia reemplaza a la anterior desde su fecha; una vigencia que ya existe se corrige.',
                submit: 'Guardar salario',
            }}
            form={store.form(tenantSlug)}
            trigger={trigger}
            above={
                grade === undefined ? undefined : (
                    <FormSection title="Vigentes y anteriores">
                        <SalaryHistory
                            salaries={grade.salaries}
                            current={grade.current}
                            label="Salarios por vigencia"
                            empty="Todavía no tiene salario. Captura abajo el de hoy."
                            removeForm={(salary) =>
                                destroy.form({ tenant: tenantSlug, salary })
                            }
                        />
                    </FormSection>
                )
            }
        >
            {(errors) => <GradeSalaryFields errors={errors} grade={grade} />}
        </CompensationSheet>
    );
}
