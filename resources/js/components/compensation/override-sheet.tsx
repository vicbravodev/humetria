import type { ReactNode } from 'react';
import CompensationSheet from '@/components/compensation/compensation-sheet';
import FormSection from '@/components/compensation/form-section';
import OverrideFields from '@/components/compensation/override-fields';
import SalaryHistory from '@/components/compensation/salary-history';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { destroy, store } from '@/routes/tenant/compensation/overrides';
import type { OverrideRow } from '@/types';

/**
 * The individual salary of a person by vigencia. Opened from a row it shows what they have earned and asks for the next
 * salary; opened from «Nuevo sueldo» (no row) it also asks for the person's ID.
 */
export default function OverrideSheet({
    person,
    open,
    onOpenChange,
    trigger,
}: {
    person?: OverrideRow;
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
                    person === undefined
                        ? 'Nuevo sueldo individual'
                        : `Sueldo de ${person.employee_number}`,
                description:
                    'Es lo que la persona gana de verdad, no una diferencia. Cada vigencia reemplaza a la anterior desde su fecha, y lo anterior se conserva.',
                submit: 'Guardar sueldo',
            }}
            form={store.form(tenantSlug)}
            trigger={trigger}
            above={
                person === undefined ? undefined : (
                    <FormSection title="Vigentes y anteriores">
                        <SalaryHistory
                            salaries={person.salaries}
                            current={person.current}
                            label="Sueldos por vigencia"
                            empty="Todavía no tiene sueldo individual."
                            removeForm={(override) =>
                                destroy.form({ tenant: tenantSlug, override })
                            }
                        />
                    </FormSection>
                )
            }
        >
            {(errors) => <OverrideFields errors={errors} person={person} />}
        </CompensationSheet>
    );
}
