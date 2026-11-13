import BelowMinimumNote from '@/components/compensation/below-minimum-note';
import GradeRowAction from '@/components/compensation/grade-row-action';
import GradeSalaryInline from '@/components/compensation/grade-salary-inline';
import {
    Table,
    TableBody,
    TableCaption,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { describeDate } from '@/lib/calendar-date';
import type { TabulatorRow } from '@/types';

/** The tabulator (board V4-Comp-Datos): each category and level with its daily salary today, since when, its people and its history. */
export default function TabulatorTable({
    grades,
    today,
}: {
    grades: TabulatorRow[];
    today: string;
}) {
    return (
        <div className="overflow-hidden card">
            <Table>
                <TableCaption className="sr-only">
                    Categorías y niveles con su salario diario vigente hoy
                </TableCaption>
                <TableHeader>
                    <TableRow>
                        <TableHead className="pl-4">Categoría</TableHead>
                        <TableHead>Nivel</TableHead>
                        <TableHead>Salario diario hoy</TableHead>
                        <TableHead>Desde</TableHead>
                        <TableHead className="text-right">Personas</TableHead>
                        <TableHead>
                            <span className="sr-only">Acciones</span>
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {grades.map((grade) => (
                        <TableRow key={grade.id}>
                            <TableCell className="pl-4 font-medium whitespace-normal">
                                {grade.category}
                            </TableCell>
                            <TableCell>{grade.level}</TableCell>
                            <TableCell>
                                {grade.current === null ? (
                                    <GradeSalaryInline
                                        grade={grade}
                                        today={today}
                                    />
                                ) : (
                                    <>
                                        <span className="font-medium tabular-nums">
                                            {grade.current.daily_salary_text}
                                        </span>
                                        {grade.current.below_minimum !==
                                            null && (
                                            <BelowMinimumNote
                                                reason={
                                                    grade.current.below_minimum
                                                }
                                            />
                                        )}
                                    </>
                                )}
                            </TableCell>
                            <TableCell className="text-muted-foreground">
                                {grade.current === null
                                    ? '—'
                                    : describeDate(
                                          grade.current.effective_from,
                                      )}
                            </TableCell>
                            <TableCell className="text-right tabular-nums">
                                {grade.people === 0 ? (
                                    <span className="text-muted-foreground">
                                        nadie todavía
                                    </span>
                                ) : (
                                    grade.people
                                )}
                            </TableCell>
                            <TableCell className="w-px pr-4 text-right">
                                <GradeRowAction grade={grade} />
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        </div>
    );
}
