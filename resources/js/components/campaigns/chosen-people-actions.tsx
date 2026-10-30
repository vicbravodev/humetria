import { Button } from '@/components/ui/button';

/** Take at once everyone the search found (when they fit), or take out everyone chosen. */
export default function ChosenPeopleActions({
    found,
    chosen,
    onChooseFound,
    onClear,
}: {
    /** People the search found that can be chosen at once; 0 when none, or too many. */
    found: number;
    chosen: number;
    onChooseFound: () => void;
    onClear: () => void;
}) {
    return (
        <div className="mt-1.5 flex flex-wrap items-center gap-2 empty:hidden">
            {found > 0 && (
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={onChooseFound}
                >
                    Elegir a{' '}
                    {found === 1
                        ? 'la persona encontrada'
                        : `las ${found} encontradas`}
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
