import { Plus } from 'lucide-react';
import { useState } from 'react';
import GradeSalarySheet from '@/components/compensation/grade-salary-sheet';
import { Button } from '@/components/ui/button';
import { SheetTrigger } from '@/components/ui/sheet';

/** «Nuevo salario»: the salary of a category and level the tabulator does not list yet. */
export default function NewSalaryButton() {
    const [open, setOpen] = useState(false);

    return (
        <GradeSalarySheet
            open={open}
            onOpenChange={setOpen}
            trigger={
                <SheetTrigger asChild>
                    <Button>
                        <Plus />
                        Nuevo salario
                    </Button>
                </SheetTrigger>
            }
        />
    );
}
