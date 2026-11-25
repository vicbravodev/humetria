import { CameraOff } from 'lucide-react';
import { Button } from '@/components/ui/button';

/**
 * The reader when the phone does not let Humetría use the camera: what is off, where to turn it on, that Marcar may still
 * work without it (when the person's center allows), and the two ways on: try again once it is allowed, or go back to Marcar.
 */
export default function QrDenied({
    onRetry,
    onClose,
}: {
    onRetry: () => void;
    onClose: () => void;
}) {
    return (
        <div className="relative flex flex-1 flex-col items-center gap-6 bg-background p-6 pb-[max(1.5rem,env(safe-area-inset-bottom))]">
            <div className="flex w-full max-w-md flex-1 flex-col items-center justify-center gap-4 text-center">
                <span
                    aria-hidden
                    className="flex size-24 items-center justify-center rounded-full bg-muted text-muted-foreground"
                >
                    <CameraOff className="size-11" />
                </span>
                <h2 className="text-[26px] leading-[34px] font-semibold tracking-[-0.01em]">
                    La cámara está apagada
                </h2>
                <p className="text-base text-pretty text-muted-foreground">
                    Humetría no puede leer el código sin la cámara. En iPhone:
                    Ajustes › Humetría › Cámara. En Android: Ajustes › Apps ›
                    Humetría › Permisos. Después regrese y toque «Intentar de
                    nuevo».
                </p>
                <p className="rounded-xl bg-muted/50 p-3 text-base text-pretty">
                    <span className="font-medium">
                        Sin cámara también puede marcar
                    </span>{' '}
                    cuando su centro lo permite: se toma con su ubicación.
                </p>
            </div>
            <div className="flex w-full max-w-md flex-col gap-2">
                <Button size="lg" className="h-14 w-full" onClick={onRetry}>
                    Intentar de nuevo
                </Button>
                <Button
                    variant="outline"
                    size="lg"
                    className="h-14 w-full"
                    onClick={onClose}
                >
                    Volver a Marcar
                </Button>
            </div>
        </div>
    );
}
