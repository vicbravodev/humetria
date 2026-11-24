import { Check, ScanLine } from 'lucide-react';
import { useState } from 'react';
import QrScanner from '@/components/attendance/qr-scanner';
import { Button } from '@/components/ui/button';
import { workerSlug } from '@/lib/pwa/sw-rules';

/**
 * «Leer el código de la pantalla»: opens the camera inside the app to read the QR of the screen of the person's center, so
 * the code travels with the mark without leaving the page (a phone's own camera app would open a new page and lose where
 * the person was). A code read is said once, and the mark that follows carries it. Nothing for a remote person (the page
 * does not render this) nor outside a company's pages.
 */
export default function PunchScanButton({
    read,
    onCode,
}: {
    /** A code was already read in this page. */
    read: boolean;
    onCode: (code: string) => void;
}) {
    const [open, setOpen] = useState(false);
    const slug = workerSlug(location.pathname);

    if (slug === null) {
        return null;
    }

    return (
        <>
            <Button
                variant="outline"
                className="h-12 w-full text-base"
                onClick={() => setOpen(true)}
            >
                <ScanLine aria-hidden className="size-5" />
                Leer el código de la pantalla
            </Button>
            {read && (
                <p
                    role="status"
                    className="flex items-center justify-center gap-1.5 text-center text-base font-medium text-primary"
                >
                    <Check aria-hidden className="size-4" />
                    Código leído. Marque ahora.
                </p>
            )}
            {open && (
                <QrScanner
                    slug={slug}
                    onCode={(code) => {
                        setOpen(false);
                        onCode(code);
                    }}
                    onClose={() => setOpen(false)}
                />
            )}
        </>
    );
}
