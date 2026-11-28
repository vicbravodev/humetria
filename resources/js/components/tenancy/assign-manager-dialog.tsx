import { useState } from 'react';
import ChoiceField from '@/components/humetria/choice-field';
import FormDialog from '@/components/humetria/form-dialog';
import { countOf } from '@/lib/plural';
import { accountManager } from '@/routes/companies';
import type { StaffMember } from '@/types';

/** Says who at Humetría answers for the chosen companies, or that nobody does. */
export default function AssignManagerDialog({
    companies,
    staff,
    open,
    onOpenChange,
}: {
    companies: string[];
    staff: StaffMember[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const [chosen, setChosen] = useState<string | null>(null);

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title="Asignar responsable"
            description={`Quien responde por ${countOf(companies.length, 'la empresa elegida', 'las empresas elegidas')} dentro de Humetría.`}
            form={accountManager.form()}
            submit="Asignar"
            blocked={chosen === null}
        >
            {(errors) => (
                <>
                    {companies.map((id) => (
                        <input
                            key={id}
                            type="hidden"
                            name="companies[]"
                            value={id}
                        />
                    ))}
                    <ChoiceField
                        name="user_id"
                        legend="Responsable"
                        options={[
                            ...staff.map((member) => ({
                                value: String(member.id),
                                label: member.name,
                            })),
                            { value: '', label: 'Nadie (quitar responsable)' },
                        ]}
                        value={chosen}
                        onChange={setChosen}
                        error={errors['user_id'] ?? errors['companies']}
                    />
                </>
            )}
        </FormDialog>
    );
}
