import { TableCell } from '@/components/ui/table';
import type { MeasureRow } from '@/types';

/** The first cell of a measure: its kind, what it says if it is «other», and what it is for. */
export default function MeasureWhat({ measure }: { measure: MeasureRow }) {
    const subtle = 'block text-caption font-normal text-muted-foreground';

    return (
        <TableCell className="font-medium whitespace-normal">
            {measure.kindLabel}
            {measure.note !== null && (
                <span className={subtle}>{measure.note}</span>
            )}
            <span className={subtle}>
                {measure.fromAssessment
                    ? 'De una evaluación individual'
                    : (measure.positionName ?? '')}
            </span>
        </TableCell>
    );
}
