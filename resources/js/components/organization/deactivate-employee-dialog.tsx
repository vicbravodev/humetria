import { useState } from 'react';
import ConfirmDialog from '@/components/humetria/confirm-dialog';
import EmployeeChoiceField from '@/components/organization/employee-choice-field';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { countOf } from '@/lib/plural';
import { serverSource } from '@/lib/server-options';
import { deactivate } from '@/routes/tenant/employees';
import { options as peopleOptions } from '@/routes/tenant/org-chart/people';
import type { EmployeeRow } from '@/types';

/**
 * Asks for confirmation before deactivating an employee. Nothing is deleted. Someone with people who report to them
 * says first whom those will report to: their own manager unless another is chosen, one of the reports included.
 */
export default function DeactivateEmployeeDialog({
    employee,
    open,
    onOpenChange,
}: {
    employee: EmployeeRow;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const tenantSlug = useTenantSlug();
    const [replacement, setReplacement] = useState(
        employee.manager?.value ?? '',
    );
    const managed = employee.direct_reports > 0;
    // Only the person is left out: whoever reports to them may take their place.
    const people = `${peopleOptions.url(tenantSlug)}?${new URLSearchParams({ except: employee.id, keep_reports: '1' }).toString()}`;

    return (
        <ConfirmDialog
            open={open}
            onOpenChange={onOpenChange}
            title={`¿Dar de baja a ${employee.name}?`}
            description="Ya no recibirá encuestas ni contará en su centro de trabajo. Su historia se conserva y puedes reactivarle cuando quieras."
            confirmLabel="Dar de baja"
            form={deactivate.form({
                tenant: tenantSlug,
                employee: employee.id,
            })}
            transform={() => (managed ? { reassign_to: replacement } : {})}
            disabled={managed && replacement === ''}
            preserveScroll
        >
            {managed && (
                <div className="grid gap-3">
                    <p className="text-sm">
                        {employee.name} tiene{' '}
                        {countOf(
                            employee.direct_reports,
                            'reporte directo',
                            'reportes directos',
                        )}
                        . Elige a quién le reportarán.
                    </p>
                    <EmployeeChoiceField
                        name="reassign_to"
                        label="Nuevo jefe directo"
                        source={serverSource(
                            people,
                            employee.manager === null ? [] : [employee.manager],
                        )}
                        defaultValue={employee.manager ?? undefined}
                        error={undefined}
                        onChange={(option) => setReplacement(option.value)}
                    />
                </div>
            )}
        </ConfirmDialog>
    );
}
