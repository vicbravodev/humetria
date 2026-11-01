import { Video } from 'lucide-react';
import { useState } from 'react';
import ReviewDecisionForm from '@/components/telework/review-decision-form';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { store } from '@/routes/tenant/telework/workplaces/remote-check';

/** Records a remote check the Comisión did (a call, a video) and its decision; it closes the ones asked for. */
export default function ReviewRemoteCheckDialog({
    tenantSlug,
    workplaceId,
}: {
    tenantSlug: string;
    workplaceId: string;
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline">
                    <Video aria-hidden />
                    Registrar comprobación a distancia
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>
                    Registrar una comprobación a distancia
                </DialogTitle>
                <DialogDescription>
                    Anota qué se vio y decide sobre el lugar.
                </DialogDescription>
                <ReviewDecisionForm
                    action={store.url({
                        tenant: tenantSlug,
                        workplace: workplaceId,
                    })}
                    idPrefix="remote"
                    submitLabel="Registrar comprobación"
                    onSuccess={() => setOpen(false)}
                />
            </DialogContent>
        </Dialog>
    );
}
