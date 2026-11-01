import { Button } from '@/components/ui/button';

/** Who is ticked to record on paper: take at once everyone the pending list found, or take out everyone chosen. */
export default function AckChoiceBar({
    found,
    chosen,
    onChooseFound,
    onClear,
}: {
    /** Pending people the search found; 0 when the view is not the pending one. */
    found: number;
    chosen: number;
    onChooseFound: () => void;
    onClear: () => void;
}) {
    return (
        <div className="flex flex-wrap items-center gap-2">
            <p className="text-sm text-muted-foreground">
                {chosen} {chosen === 1 ? 'elegida' : 'elegidas'}
            </p>
            {found > 0 && (
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={onChooseFound}
                >
                    Elegir a{' '}
                    {found === 1 ? 'la persona' : `las ${found} personas`}{' '}
                    pendientes
                </Button>
            )}
            {chosen > 0 && (
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={onClear}
                >
                    Quitar a todas
                </Button>
            )}
        </div>
    );
}
