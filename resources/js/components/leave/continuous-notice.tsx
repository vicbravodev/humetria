import { Info } from 'lucide-react';

/** The reminder of the twelve days in a row of LFT art. 78: it informs, it never stops the request. */
export default function ContinuousNotice() {
    return (
        <p className="flex items-start gap-2 text-base text-muted-foreground">
            <Info aria-hidden className="mt-1 size-4 shrink-0" />
            La ley le da derecho a 12 días seguidos de vacaciones. Dividirlos es
            decisión suya.
        </p>
    );
}
