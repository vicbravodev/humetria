import { useState } from 'react';
import ChoiceField from '@/components/humetria/choice-field';
import FormDialog from '@/components/humetria/form-dialog';
import { trialExtensionCopy } from '@/lib/tenant-work';
import { trialExtension } from '@/routes/companies';

const DAYS = [
    { value: 7, label: '7 días' },
    { value: 14, label: '14 días' },
    { value: 30, label: '30 días' },
];

/** More days of trial for the chosen companies; only those in trial, or whose trial ended, change. */
export default function ExtendTrialDialog({
    companies,
    open,
    onOpenChange,
}: {
    companies: string[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const [days, setDays] = useState(7);

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={trialExtensionCopy(companies.length)}
            description="Una prueba en curso suma los días a su fin; una que ya terminó vuelve a empezar hoy. Las que pagan o están restringidas no cambian."
            form={trialExtension.form()}
            submit="Extender prueba"
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
                        name="days"
                        legend="Días de más"
                        options={DAYS}
                        value={days}
                        onChange={setDays}
                        columns={3}
                        error={errors['days'] ?? errors['companies']}
                    />
                </>
            )}
        </FormDialog>
    );
}
