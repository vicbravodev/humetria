import { Form } from '@inertiajs/react';
import { UserPlus } from 'lucide-react';
import { useState } from 'react';
import Combobox from '@/components/humetria/combobox';
import InputError from '@/components/humetria/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import type { Option } from '@/lib/option-search';
import { share } from '@/routes/tenant/complaints/cases';

/** Gives the case to another handler: only the ones the conflict leaves and who do not have it yet are offered. */
export default function ShareCaseDialog({
    caseId,
    candidates,
}: {
    caseId: string;
    candidates: Option[];
}) {
    const tenantSlug = useTenantSlug();
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline" disabled={candidates.length === 0}>
                    <UserPlus aria-hidden />
                    Compartir
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Compartir este caso</DialogTitle>
                <DialogDescription>
                    La persona que elijas podrá leerlo y quedará en el
                    historial. Solo se ofrecen quienes atienden y no están en
                    conflicto con esta queja.
                </DialogDescription>
                <Form
                    {...share.form({ tenant: tenantSlug, complaint: caseId })}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setOpen(false)}
                    className="flex flex-col gap-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <Combobox
                                id="share-person"
                                name="user_id"
                                source={{ options: candidates }}
                                placeholder="Elige a la persona"
                                invalid={errors['user_id'] !== undefined}
                            />
                            <InputError message={errors['user_id']} />
                            <Button disabled={processing} className="self-end">
                                {processing && <Spinner />}
                                Compartir
                            </Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
