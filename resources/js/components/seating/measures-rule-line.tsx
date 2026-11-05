import { Check } from 'lucide-react';
import Notice from '@/components/humetria/notice';
import { ruleLine } from '@/lib/seating/plan';
import type { MeasuresRule } from '@/lib/seating/plan';

/** Over the measures (board LS-Plan): whether each assessment of a medium or high level has its measure (R7). */
export default function MeasuresRuleLine({ rule }: { rule: MeasuresRule }) {
    const { met, text } = ruleLine(rule);

    if (!met) {
        return (
            <Notice>
                <strong>R7 falta:</strong> {text}
            </Notice>
        );
    }

    return (
        <p
            role="status"
            className="flex items-center gap-3 rounded-xl bg-accent px-5 py-3.5 text-meta text-accent-foreground"
        >
            <Check aria-hidden className="size-5 shrink-0 text-primary" />
            <span>
                <strong className="font-semibold">R7 cumple:</strong> {text}
            </span>
        </p>
    );
}
