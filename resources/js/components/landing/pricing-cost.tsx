import { m, useInView } from 'motion/react';
import { useRef } from 'react';
import { QUOTE_ABOVE } from '@/components/landing/landing-pricing';
import PricingReason from '@/components/landing/pricing-reason';
import { useAnimatedPesos } from '@/hooks/use-animated-pesos';
import { formatPesos, monthlyCost } from '@/lib/pricing';
import { cn } from '@/lib/utils';

type Props = {
    rate: number;
    minimum: number;
    people: number;
    /** In a table cell: a smaller figure, so four plans fit side by side. */
    compact?: boolean;
};

/**
 * What the company pays a month with this plan: the figure (it counts up as it arrives and glides when the headcount
 * changes) and in a line why (the minimum, or people times the rate). Screen readers hear the final amount once, never the count.
 */
export default function PricingCost({
    rate,
    minimum,
    people,
    compact = false,
}: Props) {
    const ref = useRef<HTMLParagraphElement>(null);
    const visible = useInView(ref, { once: true, amount: 0.6 });
    const quoted = people > QUOTE_ABOVE;
    const total = monthlyCost(rate, minimum, people);
    const text = useAnimatedPesos(total, visible);
    const atMinimum = total === minimum;

    return (
        <p ref={ref} className="flex flex-col gap-1">
            <span className="sr-only" aria-live="polite">
                {quoted
                    ? 'Cotiza a tu medida'
                    : `${formatPesos(total)} MXN al mes`}
            </span>
            <span aria-hidden className="flex flex-wrap items-baseline gap-x-2">
                {quoted ? (
                    <span
                        className={cn(
                            'leading-none font-semibold tracking-[-0.03em]',
                            compact ? 'text-lg' : 'text-[32px]',
                        )}
                    >
                        Cotiza a tu medida
                    </span>
                ) : (
                    <>
                        <m.span
                            className={cn(
                                'leading-none font-semibold tracking-[-0.03em] tabular-nums',
                                compact ? 'text-[28px]' : 'text-[40px]',
                            )}
                        >
                            {text}
                        </m.span>
                        <span className="text-meta text-muted-foreground">
                            MXN al mes
                        </span>
                    </>
                )}
            </span>
            {quoted ? (
                <PricingReason key="quote">{`Con más de ${QUOTE_ABOVE} personas`}</PricingReason>
            ) : atMinimum ? (
                <PricingReason key="minimum">
                    {`Mínimo del plan (${people} × ${formatPesos(rate)} = ${formatPesos(rate * people)})`}
                </PricingReason>
            ) : (
                <PricingReason key="rate">
                    {`${people} personas × ${formatPesos(rate)}`}
                </PricingReason>
            )}
        </p>
    );
}
