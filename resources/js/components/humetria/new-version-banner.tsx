import { RefreshCw } from 'lucide-react';
import { Button } from '@/components/ui/button';

/** «Hay una versión nueva»: a floating card at the top of the installed app that loads it when the person says so, never by itself. */
export default function NewVersionBanner({
    onUpdate,
}: {
    onUpdate: () => void;
}) {
    return (
        <div
            role="status"
            className="fixed inset-x-3 top-[calc(env(safe-area-inset-top)+0.75rem)] z-50 mx-auto flex max-w-md items-center gap-3 rounded-2xl border bg-card p-3 shadow-lg"
        >
            <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-accent text-primary">
                <RefreshCw aria-hidden className="size-5" />
            </span>
            <span className="flex min-w-0 flex-1 flex-col">
                <span className="text-base font-semibold">
                    Hay una versión nueva
                </span>
                <span className="text-sm text-muted-foreground">
                    Se actualiza en un segundo
                </span>
            </span>
            <Button
                className="h-11 px-4 text-base font-bold"
                onClick={onUpdate}
            >
                Actualizar
            </Button>
        </div>
    );
}
