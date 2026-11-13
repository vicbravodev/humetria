import { useState } from 'react';
import ConfirmDialog from '@/components/humetria/confirm-dialog';
import EmployeeChoiceField from '@/components/organization/employee-choice-field';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { deactivationReasons, localToday } from '@/lib/employees';
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
    const today = localToday();
    const [terminatedOn, setTerminatedOn] = useState(today);
    const [reason, setReason] = useState('');
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
            transform={() => ({
                terminated_on: terminatedOn,
                reason,
                ...(managed ? { reassign_to: replacement } : {}),
            })}
            disabled={
                reason === '' ||
                terminatedOn === '' ||
                (managed && replacement === '')
            }
            preserveScroll
        >
            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="terminated-on">Último día de trabajo</Label>
                    <Input
                        id="terminated-on"
                        type="date"
                        value={terminatedOn}
                        max={today}
                        {...(employee.hired_on === null
                            ? {}
                            : { min: employee.hired_on })}
                        onChange={(event) => setTerminatedOn(event.target.value)}
                    />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="deactivation-reason">Motivo</Label>
                    <Select value={reason} onValueChange={setReason}>
                        <SelectTrigger id="deactivation-reason">
                            <SelectValue placeholder="Elige un motivo" />
                        </SelectTrigger>
                        <SelectContent>
                            {deactivationReasons.map((option) => (
                                <SelectItem
                                    key={option.value}
                                    value={option.value}
                                >
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
                <p className="text-sm text-muted-foreground sm:col-span-2">
                    Los días de vacaciones pendientes se calculan a esta fecha.
                </p>
            </div>
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
