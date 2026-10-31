import { House } from 'lucide-react';
import { useState } from 'react';
import DatePicker from '@/components/humetria/date-picker';
import ReviewDecisionForm from '@/components/telework/review-decision-form';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { store } from '@/routes/tenant/telework/workplaces/visit';

/** Records an in-person visit under an authorization the person already gave, with the decision it led to. */
export default function ReviewVisitDialog({
    tenantSlug,
    workplaceId,
    authorizationId,
}: {
    tenantSlug: string;
    workplaceId: string;
    authorizationId: string;
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button size="sm">
                    <House aria-hidden />
                    Registrar la visita
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Registrar la visita</DialogTitle>
                <DialogDescription>
                    La visita cuenta con la autorización escrita de la persona.
                </DialogDescription>
                <ReviewDecisionForm
                    action={store.url({
                        tenant: tenantSlug,
                        workplace: workplaceId,
                    })}
                    idPrefix="visit"
                    submitLabel="Registrar visita"
                    onSuccess={() => setOpen(false)}
                    extra={
                        <>
                            <input
                                type="hidden"
                                name="visit_authorization_id"
                                value={authorizationId}
                            />
                            <div className="grid gap-2">
                                <Label htmlFor="visit-visited-on">
                                    Fecha de la visita
                                </Label>
                                <DatePicker
                                    id="visit-visited-on"
                                    name="visited_on"
                                />
                            </div>
                        </>
                    }
                />
            </DialogContent>
        </Dialog>
    );
}
