import InputError from '@/components/humetria/input-error';
import PolicyAdvanceFields from '@/components/leave/policy-advance-fields';
import PolicyNumberField from '@/components/leave/policy-number-field';
import PolicyWorkWeekField from '@/components/leave/policy-work-week-field';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { LeavePolicyData } from '@/types';

/** What a policy says besides its rows: name, premium, working week, advances and what happens with the extra days. */
export default function PolicyBasics({
    policy,
    errors,
}: {
    policy: LeavePolicyData | null;
    errors: Partial<Record<string, string>>;
}) {
    return (
        <div className="grid gap-8">
            <section
                aria-labelledby="policy-basics"
                className="grid gap-5 card p-5"
            >
                <h2
                    id="policy-basics"
                    className="text-lg leading-[26px] font-semibold"
                >
                    Lo básico
                </h2>
                <div className="grid gap-2">
                    <Label htmlFor="policy-name">Nombre</Label>
                    <Input
                        id="policy-name"
                        name="name"
                        maxLength={120}
                        defaultValue={policy?.name}
                        aria-invalid={errors['name'] !== undefined || undefined}
                    />
                    <InputError message={errors['name']} />
                </div>
                <div className="grid gap-5 sm:grid-cols-2">
                    <PolicyNumberField
                        name="premium_percent"
                        label="Prima vacacional (%)"
                        hint="Mínimo 25 % (LFT art. 80)."
                        min={25}
                        defaultValue={policy?.premiumPercent ?? 25}
                        error={errors['premium_percent']}
                    />
                    <PolicyWorkWeekField
                        defaultValue={policy?.workWeek ?? 'mon_fri'}
                        error={errors['work_week']}
                    />
                </div>
            </section>
            <section
                aria-labelledby="policy-extra"
                className="grid gap-5 card p-5"
            >
                <h2
                    id="policy-extra"
                    className="text-lg leading-[26px] font-semibold"
                >
                    Días extra
                </h2>
                <PolicyAdvanceFields policy={policy} errors={errors} />
            </section>
        </div>
    );
}
