import PolicyNumberField from '@/components/leave/policy-number-field';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import type { LeavePolicyData } from '@/types';

/** What a policy allows beyond the law: what happens with the extra days and advancing vacations. */
export default function PolicyAdvanceFields({
    policy,
    errors,
}: {
    policy: LeavePolicyData | null;
    errors: Partial<Record<string, string>>;
}) {
    return (
        <>
            <div className="grid gap-5 sm:grid-cols-2">
                <PolicyNumberField
                    name="extra_expires_months"
                    label="Meses para usar los días extra"
                    hint="Vacío: los días extra no vencen."
                    min={1}
                    defaultValue={policy?.extraExpiresMonths}
                    error={errors['extra_expires_months']}
                />
                <PolicyNumberField
                    name="extra_carryover_cap_days"
                    label="Tope de días extra que pasan al año siguiente"
                    hint="Vacío: sin tope."
                    min={0}
                    defaultValue={policy?.extraCarryoverCapDays}
                    error={errors['extra_carryover_cap_days']}
                />
            </div>
            <div className="flex items-center gap-3">
                <Checkbox
                    id="policy-advance"
                    name="allows_advance"
                    value="1"
                    defaultChecked={policy?.allowsAdvance ?? false}
                />
                <Label htmlFor="policy-advance">
                    Permitir adelantar vacaciones (con autorización de quien
                    aprueba)
                </Label>
            </div>
        </>
    );
}
