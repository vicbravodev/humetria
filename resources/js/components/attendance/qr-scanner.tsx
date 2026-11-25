import { X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import QrStatusLine from '@/components/attendance/qr-status-line';
import QrViewfinder from '@/components/attendance/qr-viewfinder';
import { Button } from '@/components/ui/button';
import { useQrScanner } from '@/hooks/use-qr-scanner';
import { scanView } from '@/lib/pwa/qr-scan-view';
import { cn } from '@/lib/utils';

/** How long the check is shown before the reader gives way to Marcar: enough to see the code was read. */
const FOUND_MS = 550;

/**
 * The camera over the page, to read the QR of the screen of the person's center: the video full screen with a window that shows
 * where to point (it sweeps while it looks, turns amber when something needs the person's hand and shows a check when the code
 * is read), one line that says what to do or what went wrong, and the way to close it. It opens when it appears and the
 * camera is off the moment the code is read (the check stays a moment), it is closed, or the page is left.
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
    const [read, setRead] = useState(false);
    const pending = useRef<ReturnType<typeof setTimeout> | undefined>(
        undefined,
    );
    const { videoRef, start, stop, status } = useQrScanner({
        slug,
        onCode: (code) => {
            setRead(true);
            navigator.vibrate?.(40);
            pending.current = setTimeout(() => onCode(code), FOUND_MS);
        },
    });
    const view = scanView(status, read);

    useEffect(() => {
        void start();
    }, [start]);

    useEffect(() => () => clearTimeout(pending.current), []);

    return (
        <div
            role="dialog"
            aria-modal="true"
            aria-label="Leer el código de la pantalla"
            className="fixed inset-0 z-50 flex flex-col bg-background"
        >
            <video
                ref={videoRef}
                playsInline
                muted
                aria-label="Vista de la cámara"
                className={cn(
                    'absolute inset-0 size-full object-cover',
                    !view.seeing && 'hidden',
                )}
            />
            <div className="relative flex flex-1 items-center justify-center">
                {(view.seeing || read) && <QrViewfinder state={view.state} />}
            </div>
            <div className="relative mx-auto flex w-full max-w-md flex-col gap-3 p-4 pb-[max(1rem,env(safe-area-inset-bottom))]">
                <QrStatusLine
                    text={view.text}
                    opening={status === 'opening' && !read}
                    problem={view.problem}
                    shown={view.seeing || read}
                />
                <Button
                    variant="outline"
                    size="lg"
                    className="w-full bg-background"
                    onClick={() => {
                        clearTimeout(pending.current);
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
