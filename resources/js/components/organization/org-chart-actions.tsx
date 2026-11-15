import { Check, Pencil } from 'lucide-react';
import { Button } from '@/components/ui/button';

/** On the chart: «Editar estructura» (for whoever may edit), which becomes «Listo» while editing. */
export default function OrgChartActions({
    edit,
}: {
    edit: { editing: boolean; onChange: (editing: boolean) => void };
}) {
    return edit.editing ? (
        <Button onClick={() => edit.onChange(false)}>
            <Check />
            Listo
        </Button>
    ) : (
        <Button variant="outline" onClick={() => edit.onChange(true)}>
            <Pencil />
            Editar estructura
        </Button>
    );
}
