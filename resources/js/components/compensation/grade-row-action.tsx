import { SlidersHorizontal } from 'lucide-react';
import { useState } from 'react';
import GradeSalarySheet from '@/components/compensation/grade-salary-sheet';
import { Button } from '@/components/ui/button';
import { SheetTrigger } from '@/components/ui/sheet';
import { gradeName } from '@/lib/compensation/salaries';
import type { TabulatorRow } from '@/types';

/** Opens the salaries of a category and level: what they have been and the next one. */
export default function GradeRowAction({ grade }: { grade: TabulatorRow }) {
    const [open, setOpen] = useState(false);

    return (
        <GradeSalarySheet
            grade={grade}
            open={open}
            onOpenChange={setOpen}
            trigger={
                <SheetTrigger asChild>
                    <Button
                        variant="ghost"
                        size="sm"
                        aria-label={`Salarios de ${gradeName(grade)}`}
                        data-test="grade-salaries"
                    >
                        <SlidersHorizontal />
                        Salarios
                    </Button>
                </SheetTrigger>
            }
        />
    );
}
