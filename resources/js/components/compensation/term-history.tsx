import { TriangleAlert } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { describeDate } from '@/lib/calendar-date';
import { countOf } from '@/lib/plural';
import type { BenefitTerm } from '@/types';

/** The terms a benefit has had, latest vigencia first, each with a note when it falls below what the law sets. */
export default function TermHistory({ terms }: { terms: BenefitTerm[] }) {
    if (terms.length === 0) {
        return (
            <p className="rounded-xl border border-dashed px-4 py-5 text-center text-sm text-muted-foreground">
                Todavía no tiene términos. Captura abajo los de hoy.
            </p>
        );
    }

    return (
        <ol aria-label="Términos por vigencia" className="grid gap-2">
            {terms.map((term) => (
                <li
                    key={term.id}
                    className="grid gap-1.5 rounded-xl border bg-card px-4 py-3"
                >
                    <div className="flex flex-wrap items-baseline justify-between gap-x-4">
                        <span className="text-sm text-muted-foreground">
                            Desde el {describeDate(term.effective_from)}
                        </span>
                        <span className="font-medium tabular-nums">
                            {term.value_text}
                        </span>
                    </div>
                    {term.tenure_table !== null && (
                        <p className="text-caption text-muted-foreground">
                            Con tabla por antigüedad:{' '}
                            {countOf(
                                term.tenure_table.length,
                                'renglón',
                                'renglones',
                            )}
                            .
                        </p>
                    )}
                    {term.warning !== null && (
                        <Badge
                            variant="warning"
                            className="h-auto py-1 whitespace-normal"
                        >
                            <TriangleAlert aria-hidden />
                            {term.warning}
                        </Badge>
                    )}
                </li>
            ))}
        </ol>
    );
}
