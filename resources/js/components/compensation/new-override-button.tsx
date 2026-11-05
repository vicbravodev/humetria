import { Plus } from 'lucide-react';
import { useState } from 'react';
import OverrideSheet from '@/components/compensation/override-sheet';
import { Button } from '@/components/ui/button';
import { SheetTrigger } from '@/components/ui/sheet';

/** «Nuevo sueldo»: the individual salary of a person who does not have one yet. */
export default function NewOverrideButton() {
    const [open, setOpen] = useState(false);

    return (
        <OverrideSheet
            open={open}
            onOpenChange={setOpen}
            trigger={
                <SheetTrigger asChild>
                    <Button>
                        <Plus />
                        Nuevo sueldo
                    </Button>
                </SheetTrigger>
            }
        />
    );
}
