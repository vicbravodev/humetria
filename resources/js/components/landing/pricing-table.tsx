import {
    PRICING_PLANS,
    PRICING_ROWS,
} from '@/components/landing/landing-pricing';
import PricingCost from '@/components/landing/pricing-cost';
import PricingTableHead, {
    column,
} from '@/components/landing/pricing-table-head';
import PricingTableRow, {
    ROW_HEAD,
} from '@/components/landing/pricing-table-row';
import { cn } from '@/lib/utils';

/**
 * The plans side by side: what each costs for the headcount, then what each includes, line by line. A real table, so a
 * screen reader reads «Compensación, Integral: incluido». On a phone it scrolls sideways with the line names fixed on the left.
 */
export default function PricingTable({ people }: { people: number }) {
    return (
        <div
            role="region"
            aria-label="Comparación de planes"
            // The table scrolls sideways on a phone and holds nothing to focus, so the region takes the focus itself. It is `relative`
            // so the hidden texts of its cells (absolute) are held and clipped by it, instead of stretching the page.
            tabIndex={0}
            className="relative -mx-5 overflow-x-auto px-5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring sm:-mx-8 sm:px-8 lg:mx-0 lg:px-0"
        >
            <table className="w-full min-w-[760px] border-collapse text-meta">
                <caption className="sr-only">
                    Lo que cuesta y lo que incluye cada plan con {people}{' '}
                    personas
                </caption>
                <PricingTableHead />
                <tbody>
                    <tr className="border-t">
                        <th scope="row" className={ROW_HEAD}>
                            Tu costo al mes
                        </th>
                        {PRICING_PLANS.map((plan) => (
                            <td
                                key={plan.id}
                                className={cn(
                                    'px-4 py-4 align-top',
                                    column(plan),
                                )}
                            >
                                <PricingCost
                                    rate={plan.rate}
                                    minimum={plan.minimum}
                                    people={people}
                                    compact
                                />
                            </td>
                        ))}
                    </tr>
                    <tr className="border-t">
                        <th scope="row" className={ROW_HEAD}>
                            Centros de trabajo incluidos
                        </th>
                        {PRICING_PLANS.map((plan) => (
                            <td
                                key={plan.id}
                                className={cn(
                                    'px-4 py-3.5 tabular-nums',
                                    column(plan),
                                )}
                            >
                                {plan.centers}
                            </td>
                        ))}
                    </tr>
                    {PRICING_ROWS.map((row, index) => (
                        <PricingTableRow
                            key={row.label}
                            row={row}
                            last={index === PRICING_ROWS.length - 1}
                        />
                    ))}
                </tbody>
            </table>
        </div>
    );
}
