import { Check, Minus } from 'lucide-react';
import {
    PRICING_PLANS,
    planHas,
    type PricingRow,
} from '@/components/landing/landing-pricing';
import { column } from '@/components/landing/pricing-table-head';
import { cn } from '@/lib/utils';

export const ROW_HEAD =
    'sticky left-0 z-1 bg-background py-3.5 pr-4 text-left font-medium';

type Props = { row: PricingRow; last: boolean };

/** One line of the comparison: what it names, and for each plan whether it has it (a check, or a dash) said aloud too. */
export default function PricingTableRow({ row, last }: Props) {
    return (
        <tr className="border-t">
            <th scope="row" className={ROW_HEAD}>
                {row.label}
            </th>
            {PRICING_PLANS.map((plan) => {
                const has = planHas(plan, row);

                return (
                    <td
                        key={plan.id}
                        className={cn(
                            'px-4 py-3.5',
                            column(plan),
                            plan.featured && last && 'rounded-b-xl',
                        )}
                    >
                        {has ? (
                            <Check
                                aria-hidden
                                className="size-5 text-primary"
                            />
                        ) : (
                            <Minus
                                aria-hidden
                                className="size-4 text-muted-foreground/60"
                            />
                        )}
                        <span className="sr-only">
                            {has ? 'Incluido' : 'No incluido'}
                        </span>
                    </td>
                );
            })}
        </tr>
    );
}
