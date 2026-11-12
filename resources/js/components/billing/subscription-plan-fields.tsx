import ChoiceField from '@/components/humetria/choice-field';
import {
    PLANS,
    planAfterProfile,
    profileAfterPlan,
    profileChoices,
} from '@/lib/billing-form';
import type { AccountKind, Plan, PriceProfile } from '@/types';

/**
 * What the company contracts and how it is priced. A channel rate only goes with its plan, so choosing one picks that plan, and
 * choosing another plan drops a channel rate that no longer fits.
 */
export default function SubscriptionPlanFields({
    accountKind,
    plan,
    profile,
    onChange,
    errors,
}: {
    accountKind: AccountKind;
    plan: Plan;
    profile: PriceProfile;
    onChange: (next: { plan: Plan; profile: PriceProfile }) => void;
    errors: Partial<Record<string, string>>;
}) {
    return (
        <div className="grid gap-5">
            <ChoiceField
                name="plan"
                legend="Plan"
                options={PLANS}
                value={plan}
                columns={3}
                error={errors['plan']}
                onChange={(value) =>
                    onChange({
                        plan: value,
                        profile: profileAfterPlan(profile),
                    })
                }
            />
            <ChoiceField
                name="profile"
                legend="Cómo se cobra"
                options={profileChoices(accountKind)}
                value={profile}
                columns={2}
                error={errors['profile']}
                onChange={(value) =>
                    onChange({
                        plan: planAfterProfile(plan, value),
                        profile: value,
                    })
                }
            />
        </div>
    );
}
