import { SlidersHorizontal } from 'lucide-react';
import { useState } from 'react';
import OverrideSheet from '@/components/compensation/override-sheet';
import { Button } from '@/components/ui/button';
import { SheetTrigger } from '@/components/ui/sheet';
import type { OverrideRow } from '@/types';

/** Opens the individual salaries of a person: what they have earned and the next one. */
export default function OverrideRowAction({ person }: { person: OverrideRow }) {
    const [open, setOpen] = useState(false);

    return (
        <OverrideSheet
            person={person}
            open={open}
            onOpenChange={setOpen}
            trigger={
                <SheetTrigger asChild>
                    <Button
                        variant="ghost"
                        size="sm"
                        aria-label={`Sueldos de ${person.employee_number}`}
                        data-test="override-salaries"
                    >
                        <SlidersHorizontal />
                        Sueldos
                    </Button>
                </SheetTrigger>
            }
        />
    );
}
