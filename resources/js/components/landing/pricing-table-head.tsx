import {
    PRICING_PLANS,
    type PricingPlan,
} from '@/components/landing/landing-pricing';
import { formatPesos } from '@/lib/pricing';
import { cn } from '@/lib/utils';

/** The shade of the plan the page recommends, in every cell of its column. */
export const column = (plan: PricingPlan): string =>
    plan.featured ? 'bg-accent' : '';

/** The names of the plans over their columns, each with its rate per person; the recommended one carries its mark. */
export default function PricingTableHead() {
    return (
        <thead>
            <tr>
                <th scope="col" className="w-[26%] pb-4 text-left">
                    <span className="sr-only">Plan</span>
                </th>
                {PRICING_PLANS.map((plan) => (
                    <th
                        key={plan.id}
                        scope="col"
                        data-test={`pricing-${plan.id}`}
                        className={cn(
                            'px-4 pt-5 pb-4 text-left align-bottom',
                            column(plan),
                            plan.featured && 'rounded-t-xl',
                        )}
                    >
                        {plan.featured ? (
                            <span className="mb-1.5 block w-fit rounded-full bg-primary px-3 py-0.5 text-xs font-medium text-primary-foreground">
                                El más completo
                            </span>
                        ) : null}
                        <span className="block text-xl leading-tight font-semibold tracking-[-0.02em]">
                            {plan.name}
                        </span>
                        <span className="block text-meta font-normal text-muted-foreground">
                            {formatPesos(plan.rate)} MXN por persona al mes
                        </span>
                    </th>
                ))}
            </tr>
        </thead>
    );
}
