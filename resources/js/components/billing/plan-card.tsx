import { Badge } from '@/components/ui/badge';
import { Check } from 'lucide-react';
import { moduleLabel, planPrice, planReach } from '@/lib/billing-catalog';
import { countOf } from '@/lib/plural';
import type { CatalogPlan } from '@/types';

/** One plan of the catalog (tablero Planes y precios): its price before VAT, who is on it and what it includes. Read only: the catalog changes in code. */
export default function PlanCard({
    plan,
    popular,
}: {
    plan: CatalogPlan;
    /** The plan with the most companies: «El más usado». */
    popular: boolean;
}) {
    const price = planPrice(plan);

    return (
        <article
            aria-labelledby={`plan-${plan.key}`}
            className="flex flex-col card"
        >
            <div className="grid gap-1 p-6 pb-4">
                <div className="flex items-center gap-3">
                    <h3
                        id={`plan-${plan.key}`}
                        className="flex-1 text-lg font-semibold tracking-[-0.01em]"
                    >
                        {plan.label}
                    </h3>
                    {popular && <Badge variant="success">El más usado</Badge>}
                </div>
                <p>
                    <span className="text-[32px] leading-10 font-semibold tracking-[-0.01em] tabular-nums">
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
