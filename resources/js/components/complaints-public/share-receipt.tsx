import { Share2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { shareText } from '@/lib/complaints/receipt';
import type { Receipt } from '@/lib/complaints/receipt';

/**
 * «Compartir»: hands the codes to the phone's own share sheet (a note, a message to oneself). Only where the browser has
 * one, and nothing is written on the device by this page: the key is not kept here (decisión 23).
 */
export default function ShareReceipt({ receipt }: { receipt: Receipt }) {
    if (typeof navigator.share !== 'function') {
        return null;
    }

    return (
        <Button
            type="button"
            variant="outline"
            className="w-full"
            onClick={() =>
                void navigator
                    .share({ text: shareText(receipt.reference, receipt.key) })
                    .catch(() => undefined)
            }
        >
            <Share2 aria-hidden />
            Compartir
        </Button>
    );
}
