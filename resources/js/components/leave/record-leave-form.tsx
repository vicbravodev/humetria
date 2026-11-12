import { useState } from 'react';
import Combobox from '@/components/humetria/combobox';
import FormDialog from '@/components/humetria/form-dialog';
import InputError from '@/components/humetria/input-error';
import RecordDates from '@/components/leave/record-dates';
import RecordTypeField from '@/components/leave/record-type-field';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { serverSource } from '@/lib/server-options';
import { options } from '@/routes/tenant/vacations/people';
import { store } from '@/routes/tenant/vacations/requests';
import type { LeaveTypeOption } from '@/types';

/**
 * RH records vacations or a leave for somebody, even days already gone: the person is looked up by name on the server, and
 * the record can be answered in the same step. Nobody is emailed to answer what RH recorded.
 */
export default function RecordLeaveForm({
    open,
    onOpenChange,
    types,
    person,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    types: LeaveTypeOption[];
    /** Who it is for when the page already says (their record); without it the person is looked up by name. */
    person?: { value: string; label: string };
}) {
    const tenantSlug = useTenantSlug();
    const [typeId, setTypeId] = useState('');
    const [startsOn, setStartsOn] = useState('');

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title="Registrar a nombre de alguien"
            description="Quedará registrado que lo hiciste tú. Puedes registrar días que ya pasaron."
            form={store.form(tenantSlug)}
            submit="Registrar solicitud"
        >
            {(errors) => (
                <div className="grid gap-5">
                    <div className="grid gap-2">
                        <Label htmlFor="record-person">Persona</Label>
                        {person === undefined ? (
                            <Combobox
                                id="record-person"
                                name="employee_id"
                                source={serverSource(
                                    options.url(tenantSlug),
                                    [],
                                )}
                                placeholder="Busca por nombre"
                                invalid={errors['employee_id'] !== undefined}
                            />
                        ) : (
                            <>
                                <input
                                    type="hidden"
                                    name="employee_id"
                                    value={person.value}
                                />
                                <p id="record-person" className="font-medium">
                                    {person.label}
                                </p>
                            </>
                        )}
                        <InputError message={errors['employee_id']} />
                    </div>
                    <RecordTypeField
                        types={types}
                        value={typeId}
                        onChange={setTypeId}
                        error={errors['leave_type_id']}
                    />
                    <RecordDates
                        startsOn={startsOn}
                        onStartsOn={setStartsOn}
                        errors={errors}
                    />
                    <div className="grid gap-2">
                        <Label htmlFor="record-note">Nota (opcional)</Label>
                        <Textarea
                            id="record-note"
                            name="note"
                            rows={2}
                            maxLength={500}
                        />
                        <InputError message={errors['note']} />
                    </div>
                    <div className="flex items-center gap-3">
                        <Checkbox
                            id="record-approve"
                            name="approve"
                            value="1"
                        />
                        <Label htmlFor="record-approve">
                            Aprobarla en este mismo paso
                        </Label>
                    </div>
                </div>
            )}
        </FormDialog>
    );
}
