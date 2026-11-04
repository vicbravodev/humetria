import { Badge } from '@/components/ui/badge';
import { describeDate } from '@/lib/calendar-date';
import { salaryStanding } from '@/lib/compensation/salaries';
import type { SalaryEntry } from '@/types';

const STANDING = {
    current: 'Vigente hoy',
    upcoming: 'Empieza después',
    past: 'Reemplazado',
} as const;

/**
 * The salaries a category or a person has had, latest vigencia first, each marked as the one in force today, one that
 * starts later or one already replaced.
 */
export default function SalaryHistory({
    salaries,
    current,
    label,
    empty,
}: {
    salaries: SalaryEntry[];
    current: SalaryEntry | null;
    label: string;
    empty: string;
}) {
    if (salaries.length === 0) {
        return (
            <p className="rounded-xl border border-dashed px-4 py-5 text-center text-sm text-muted-foreground">
                {empty}
            </p>
        );
    }

    return (
        <ol aria-label={label} className="grid gap-2">
            {salaries.map((salary) => {
                const standing = salaryStanding(
                    salary.effective_from,
                    current?.effective_from ?? null,
                );

                return (
                    <li
                        key={salary.effective_from}
                        className="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 card px-4 py-3"
                    >
                        <span className="text-sm text-muted-foreground">
                            Desde el {describeDate(salary.effective_from)}
                        </span>
                        <span className="flex items-center gap-2">
                            <Badge
                                variant={
                                    standing === 'current'
                                        ? 'default'
                                        : 'outline'
                                }
                            >
                                {STANDING[standing]}
                            </Badge>
                            <span className="font-medium tabular-nums">
                                {salary.daily_salary_text}
                            </span>
                        </span>
                    </li>
                );
            })}
        </ol>
    );
}
