import { TriangleAlert } from 'lucide-react';
import { describeDate } from '@/lib/calendar-date';
import type { BenefitRow } from '@/types';

/** The terms in force today of a benefit, since when, and a note when they fall below what the law sets. */
export default function BenefitValue({ benefit }: { benefit: BenefitRow }) {
    if (benefit.current === null) {
        return <span className="text-muted-foreground">Sin términos</span>;
    }

    return (
        <div>
            <p className="font-medium tabular-nums">
                {benefit.current.value_text}
            </p>
            <p className="text-caption text-muted-foreground">
                Desde el {describeDate(benefit.current.effective_from)}
            </p>
            {benefit.note !== null && (
                <p className="mt-1 text-caption whitespace-normal text-muted-foreground">
                    {benefit.note}
                </p>
            )}
            {benefit.policy_notice !== null && (
                <p className="mt-1 flex items-start gap-1.5 text-caption whitespace-normal text-warning-foreground">
                    <TriangleAlert
                        aria-hidden
                        className="mt-0.5 size-3.5 shrink-0"
                    />
                    {benefit.policy_notice}
                </p>
            )}
            {benefit.warning !== null && (
                <p className="mt-1 flex items-start gap-1.5 text-caption whitespace-normal text-warning-foreground">
                    <TriangleAlert
                        aria-hidden
                        className="mt-0.5 size-3.5 shrink-0"
                    />
                    {benefit.warning}
                </p>
            )}
        </div>
    );
}
