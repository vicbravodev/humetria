import RecentProofContent from '@/components/worker-access/recent-proof-content';
import { Dialog } from '@/components/ui/dialog';
import { useProofRequest } from '@/hooks/use-proof-request';

/**
 * Mounted once for every page: when a delicate form of the person comes back for want of a recent proof (the flash
 * `proofRequired`), it asks them to prove it is them and sends the form again. Closing it sends nothing.
 */
export default function RecentProofDialog() {
    const request = useProofRequest();

    return (
        <Dialog
            open={request.step !== null}
            onOpenChange={(open) => {
                if (!open) {
                    request.close();
                }
            }}
        >
            {request.step !== null && (
                <RecentProofContent
                    step={request.step}
                    onProved={request.resend}
                />
            )}
        </Dialog>
    );
}
