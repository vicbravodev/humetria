import GradeCurrentSalary from '@/components/compensation/grade-current-salary';
import GradeRowAction from '@/components/compensation/grade-row-action';
import {
    Table,
    TableBody,
    TableCaption,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { countOf } from '@/lib/plural';
import type { TabulatorRow } from '@/types';

/** The tabulator: each category and level with the daily salary in force today and how many vigencias it has had. */
export default function TabulatorTable({ grades }: { grades: TabulatorRow[] }) {
    return (
        <Table>
            <TableCaption className="sr-only">
                Categorías y niveles con su salario diario vigente hoy
            </TableCaption>
            <TableHeader>
                <TableRow>
                    <TableHead>Categoría</TableHead>
                    <TableHead>Nivel</TableHead>
                    <TableHead>Salario diario hoy</TableHead>
                    <TableHead>Vigencias</TableHead>
                    <TableHead>
                        <span className="sr-only">Acciones</span>
                    </TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {grades.map((grade) => (
                    <TableRow key={grade.id}>
                        <TableCell className="font-medium whitespace-normal">
                            {grade.category}
                        </TableCell>
                        <TableCell>{grade.level}</TableCell>
                        <TableCell>
                            <GradeCurrentSalary current={grade.current} />
                        </TableCell>
                        <TableCell className="text-muted-foreground">
                            {grade.salaries.length === 0
                                ? '—'
                                : countOf(
                                      grade.salaries.length,
                                      'vigencia',
                                      'vigencias',
                                  )}
                        </TableCell>
                        <TableCell className="w-px text-right">
                            <GradeRowAction grade={grade} />
                        </TableCell>
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}
