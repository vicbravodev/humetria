import { Form } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { peopleQuery } from '@/lib/campaigns';
import { resend } from '@/routes/tenant/campaigns';
import type { PeopleFilters } from '@/types';

/**
 * Sends a new link to the people the filters show, after saying their old link stops working. Only while the list shows
 * pending people, or those whose link failed: then how many get one is exactly how many are listed.
 */
export default function ResendFilteredDialog({
    tenantSlug,
    campaignId,
    filters,
    count,
}: {
    tenantSlug: string;
    campaignId: string;
    filters: PeopleFilters;
    count: number;
}) {
    const [open, setOpen] = useState(false);
    const people = count === 1 ? '1 persona' : `${count} personas`;

    if (!['pending', 'failed'].includes(filters.status) || count === 0) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline">Reenviar a {people}</Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>¿Reenviar el enlace?</DialogTitle>
                <DialogDescription>
                    Se enviará un enlace nuevo a {people}; el anterior deja de
                    servir.
                </DialogDescription>
                <Form
                    {...resend.form([tenantSlug, campaignId])}
                    transform={() => peopleQuery(filters)}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setOpen(false)}
                >
                    {({ processing }) => (
                        <DialogFooter className="gap-2">
                            <DialogClose asChild>
                                <Button variant="outline">Cancelar</Button>
                            </DialogClose>
                            <Button type="submit" disabled={processing}>
                                {processing && <Spinner />}
                                Reenviar enlaces
                            </Button>
                        </DialogFooter>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
