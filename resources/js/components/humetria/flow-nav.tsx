import { Button } from '@/components/ui/button';

/**
 * «Anterior» and «Siguiente» of a question flow (board V2-Politica), with «Puedes cambiarlo después». It never moves on by
 * itself: an answer only marks the option.
 */
export default function FlowNav({
    first,
    onBack,
    onNext,
}: {
    first: boolean;
    onBack: () => void;
    onNext: () => void;
}) {
    return (
        <div className="flex flex-wrap items-center gap-3 pt-2">
            <Button
                size="lg"
                variant="outline"
                onClick={onBack}
                disabled={first}
            >
                Anterior
            </Button>
            <Button size="lg" onClick={onNext}>
                Siguiente
            </Button>
            <span className="ml-2 text-sm text-muted-foreground">
                Puedes cambiarlo después
            </span>
        </div>
    );
}
