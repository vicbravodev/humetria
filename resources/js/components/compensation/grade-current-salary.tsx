import { TriangleAlert } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { describeDate } from '@/lib/calendar-date';
import type { SalaryEntry } from '@/types';

/** The salary in force today with the day it started, or a note that the category has none yet. */
export default function GradeCurrentSalary({
    current,
}: {
    current: SalaryEntry | null;
}) {
    if (current === null) {
        return <Badge variant="warning">Sin salario</Badge>;
    }

    return (
        <div>
            <p className="font-medium tabular-nums">
                {current.daily_salary_text}
            </p>
            <p className="text-caption text-muted-foreground">
                Desde el {describeDate(current.effective_from)}
            </p>
            {current.below_minimum !== null && (
                <p className="mt-1 flex items-start gap-1 rounded-md bg-warning px-2 py-1 text-caption text-warning-foreground">
                    <TriangleAlert
                        aria-hidden="true"
                        className="mt-0.5 size-3.5 shrink-0"
                    />
                    <span>
                        <span className="font-medium">
                            Bajo el salario mínimo.
                        </span>{' '}
                        {current.below_minimum}
                    </span>
                </p>
            )}
        </div>
    );
}
