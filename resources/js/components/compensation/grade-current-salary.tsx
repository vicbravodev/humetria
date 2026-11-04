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
        </div>
    );
}
