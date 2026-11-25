import { Check } from 'lucide-react';
import { moduleLabel, planPrice, planReach } from '@/lib/billing-catalog';
import { countOf } from '@/lib/plural';
import type { CatalogPlan } from '@/types';

/** One plan of the catalog: its price before VAT, who is on it and what it includes. Read only: the catalog changes in code. */
export default function PlanCard({ plan }: { plan: CatalogPlan }) {
    const price = planPrice(plan);

    return (
        <article
            aria-labelledby={`plan-${plan.key}`}
            className="flex flex-col card"
        >
            <div className="grid gap-1 p-6 pb-4">
                <h3
                    id={`plan-${plan.key}`}
                    className="text-lg font-semibold tracking-[-0.01em]"
                >
                    {plan.label}
                </h3>
                <p>
                    <span className="text-2xl font-semibold tabular-nums">
                        {price.amount}
                    </span>{' '}
                    <span className="text-muted-foreground">{price.per}</span>
                </p>
                <p className="text-caption text-muted-foreground">
                    {price.note} · {planReach(plan)}
                </p>
            </div>
            <ul className="grid gap-2 border-t px-6 py-4 text-sm">
                <li className="flex items-center gap-2">
                    <Check aria-hidden className="size-4 text-primary" />
                    {countOf(
                        plan.included_centers,
                        'centro de trabajo incluido',
                        'centros de trabajo incluidos',
                    )}
                </li>
                {plan.modules.map((module) => (
                    <li key={module} className="flex items-center gap-2">
                        <Check aria-hidden className="size-4 text-primary" />
                        {moduleLabel(module)}
                    </li>
                ))}
            </ul>
        </article>
    );
}
