import QrScanner from '@/components/attendance/qr-scanner';
import type { MarkType } from '@/lib/attendance/next-step';

/**
 * The reader that opens when the person taps a mark the policy asks the screen's code for: it reads it, and the mark goes on
 * with that code (the key comes after). Closing it leaves the page as it was, with nothing marked.
 */
export default function PunchScanFirst({
    type,
    slug,
    onRead,
    onClose,
}: {
    type: MarkType;
    slug: string;
    /** The code was read for the mark of `type`. */
    onRead: (code: string, type: MarkType) => void;
    onClose: () => void;
}) {
    return (
        <QrScanner
            slug={slug}
            onCode={(code) => onRead(code, type)}
            onClose={onClose}
        />
    );
}
