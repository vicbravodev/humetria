import { ChevronsDownUp, ChevronsUpDown } from 'lucide-react';
import { Button } from '@/components/ui/button';

/** Opens or folds every group of the checklist at once, to read the whole list before publishing. */
export default function GroupsToggle({
    onToggle,
}: {
    onToggle: (open: boolean) => void;
}) {
    return (
        <div className="-mb-4 flex justify-end gap-2">
            <Button variant="outline" onClick={() => onToggle(true)}>
                <ChevronsUpDown aria-hidden />
                Abrir todo
            </Button>
            <Button variant="outline" onClick={() => onToggle(false)}>
                <ChevronsDownUp aria-hidden />
                Cerrar todo
            </Button>
        </div>
    );
}
