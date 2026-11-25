import { X } from 'lucide-react';
import { useEffect } from 'react';
import { Button } from '@/components/ui/button';
import { useQrScanner } from '@/hooks/use-qr-scanner';
import type { ScanStatus } from '@/hooks/use-qr-scanner';
import { cn } from '@/lib/utils';

const TEXT: Record<ScanStatus, string> = {
    idle: '',
    opening: 'Abriendo la cámara…',
    scanning: 'Apunte al código de la pantalla de su centro.',
    unreadable:
        'No logro leer el código. Acérquese o aléjese un poco, sin reflejos, y mantenga el teléfono quieto.',
    unrecognized:
        'Ese código no es el de la pantalla de su centro. Apunte al de la pantalla.',
    'other-company': 'Ese código es de otra empresa.',
    denied: 'Permita el uso de la cámara para leer el código.',
    unavailable: 'Este teléfono no pudo abrir la cámara.',
};

/**
 * The camera over the page, to read the QR of the screen of the person's center: the video, one line that says what to do
 * or what went wrong, and the way to close it. It opens when it appears and the camera is off the moment the code is read,
 * it is closed, or the page is left.
 */
export default function QrScanner({
    slug,
    onCode,
    onClose,
}: {
    slug: string;
    onCode: (code: string) => void;
    onClose: () => void;
}) {
    const { videoRef, start, stop, status } = useQrScanner({ slug, onCode });
    const seeing = [
        'opening',
        'scanning',
        'unreadable',
        'unrecognized',
        'other-company',
    ].includes(status);

    useEffect(() => {
        void start();
    }, [start]);

    return (
        <div
            role="dialog"
            aria-modal="true"
            aria-label="Leer el código de la pantalla"
            className="fixed inset-0 z-50 flex flex-col gap-4 bg-background p-4"
        >
            <div className="mx-auto flex w-full max-w-md flex-1 flex-col gap-4">
                <video
                    ref={videoRef}
                    playsInline
                    muted
                    aria-label="Vista de la cámara"
                    className={cn(
                        'min-h-0 w-full flex-1 rounded-lg bg-muted object-cover',
                        !seeing && 'hidden',
                    )}
                />
                <p
                    role="status"
                    className={cn(
                        'text-center text-lg',
                        status === 'scanning' || status === 'opening'
                            ? 'text-muted-foreground'
                            : 'text-foreground',
                        !seeing && 'my-auto',
                    )}
                >
                    {TEXT[status]}
                </p>
                <Button
                    variant="outline"
                    size="lg"
                    className="w-full"
                    onClick={() => {
                        stop();
                        onClose();
                    }}
                >
                    <X aria-hidden />
                    Cerrar cámara
                </Button>
            </div>
        </div>
    );
}
