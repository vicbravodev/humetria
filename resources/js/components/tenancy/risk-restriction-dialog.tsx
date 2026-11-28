import { useState } from 'react';
import FormDialog from '@/components/humetria/form-dialog';
import InputError from '@/components/humetria/input-error';
import TenantReasonField from '@/components/tenancy/tenant-reason-field';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { restrictionCopy } from '@/lib/tenant-restriction';
import { risk } from '@/routes/companies';
import type { CompanyDetail } from '@/types';

/**
 * Suspends or archives the company from its page: the reason, and its name typed before the button wakes up, so nobody stops a
 * company by a slip of the hand. The server checks the name too.
 */
export default function RiskRestrictionDialog({
    company,
    restriction,
    open,
    onOpenChange,
}: {
    company: CompanyDetail;
    restriction: 'suspended' | 'archived';
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const copy = restrictionCopy(restriction, company.trade_name);
    const [typed, setTyped] = useState('');

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={copy.title}
            description="Sus datos se conservan; se puede reactivar después."
            form={risk.form(company.id)}
            submit={copy.submit}
            tone="destructive"
            blocked={typed.trim() !== company.trade_name}
        >
            {(errors) => (
                <>
                    <input
                        type="hidden"
                        name="restriction"
                        value={restriction}
                    />
                    <TenantReasonField error={errors['reason']} />
                    <div className="grid gap-2">
                        <Label htmlFor="risk-confirmation">
                            Escribe «{company.trade_name}» para confirmar
                        </Label>
                        <Input
                            id="risk-confirmation"
                            name="confirmation"
                            autoComplete="off"
                            value={typed}
                            onChange={(event) => setTyped(event.target.value)}
                            aria-invalid={errors['confirmation'] !== undefined}
                            aria-describedby="risk-confirmation-error"
                        />
                        <InputError
                            id="risk-confirmation-error"
                            message={errors['confirmation']}
                        />
                    </div>
                </>
            )}
        </FormDialog>
    );
}
