import { Check } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import type { MatrixCell as State } from '@/types';

/**
 * Where one thing stands in one center (board V4-Difusion): a filled check when it is ready, a dashed ring when it is missing,
 * and a word for the rest. The word is always there for a screen reader.
 */
export default function MatrixCell({
    state,
    draft,
}: {
    state: State;
    /** Its writing started and was left: missing reads «Borrador». */
    draft: boolean;
}) {
    if (state === 'done') {
        return (
            <span className="inline-flex size-6 items-center justify-center rounded-full bg-primary text-primary-foreground">
                <Check aria-hidden className="size-3.5" strokeWidth={3} />
                <span className="sr-only">Listo</span>
            </span>
        );
    }

    if (state === 'missing' && !draft) {
        return (
            <span className="inline-flex size-6 rounded-full border-[1.5px] border-dashed border-destructive">
                <span className="sr-only">Falta</span>
            </span>
        );
    }

    if (state === 'not_applicable') {
        return <span className="text-sm text-muted-foreground">No aplica</span>;
    }

    const labels: Record<State, string> = {
        done: 'Listo',
        missing: 'Borrador',
        undelivered: 'Sin difundir',
        ready: 'Lista',
        waiting: 'Sin evaluar',
        not_applicable: 'No aplica',
    };

    return <Badge variant="warning">{labels[state]}</Badge>;
}
