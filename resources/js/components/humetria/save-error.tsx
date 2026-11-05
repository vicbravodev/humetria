import { Button } from '@/components/ui/button';

/**
 * What a form says when its answers did not reach the server (V3-Estados, «No se pudo guardar»): what happened, that
 * nothing was lost, and the way to try again.
 */
export default function SaveError({
    onRetry,
    processing,
}: {
    onRetry: () => void;
    processing: boolean;
}) {
    return (
        <div role="alert" className="flex flex-col gap-4">
            <div className="rounded-xl border border-destructive px-4 py-3.5">
                <p className="font-semibold text-destructive">
                    No se guardaron tus respuestas
                </p>
                <p className="text-sm">
                    Se perdió la conexión. Tus respuestas siguen aquí; vuelve a
                    intentarlo cuando tengas internet.
                </p>
            </div>
            <div>
                <Button onClick={onRetry} disabled={processing}>
                    Reintentar
                </Button>
            </div>
        </div>
    );
}
