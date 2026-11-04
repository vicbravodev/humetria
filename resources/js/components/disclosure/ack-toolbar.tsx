import { Button } from '@/components/ui/button';
import { selectionText } from '@/lib/disclosure';

/** What RH has ticked in the list and what it can do with it: take every pending person found, clear, record on paper. */
export default function AckToolbar({
    count,
    offerAll,
    onChooseAll,
    onClear,
    onRecord,
}: {
    count: number;
    /** How many pending people the search found, when taking them at once is offered; null when it is not. */
    offerAll: number | null;
    onChooseAll: () => void;
    onClear: () => void;
    onRecord: () => void;
}) {
    return (
        <div className="flex flex-wrap items-center gap-3">
            <p className="text-meta text-muted-foreground">
                {selectionText(count)}
            </p>
            {offerAll !== null && (
                <Button variant="ghost" size="sm" onClick={onChooseAll}>
                    Elegir las {offerAll} pendientes
                </Button>
            )}
            {count > 0 && (
                <>
                    <Button variant="ghost" size="sm" onClick={onClear}>
                        Quitar la selección
                    </Button>
                    <Button size="sm" onClick={onRecord}>
                        Registrar acuses en papel
                    </Button>
                </>
            )}
        </div>
    );
}
