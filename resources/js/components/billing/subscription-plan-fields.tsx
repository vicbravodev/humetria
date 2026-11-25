import ChoiceField from '@/components/humetria/choice-field';
import {
    planAfterProfile,
    planChoices,
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
    union,
    plan,
    profile,
    onChange,
    errors,
}: {
    accountKind: AccountKind;
    /** A union's account: its plan is its own, and it is the only one it can have. */
    union: boolean;
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
                options={planChoices(union)}
                value={plan}
                columns={union ? 1 : 3}
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
                options={profileChoices(accountKind, union)}
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
