import { Form } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { archive } from '@/routes/tenant/work-centers';
import type { WorkCenterRow } from '@/types';

/** Asks for confirmation before taking a work center out of the list. Nothing is deleted. */
export default function ArchiveWorkCenterDialog({
    tenantSlug,
    workCenter,
    open,
    onOpenChange,
}: {
    tenantSlug: string;
    workCenter: WorkCenterRow;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogTitle>¿Archivar {workCenter.name}?</DialogTitle>
                <DialogDescription>
                    Dejará de aparecer en la lista. Su historia se conserva y
                    puedes restaurarlo cuando quieras.
                </DialogDescription>
                <Form
                    {...archive.form({
                        tenant: tenantSlug,
                        workCenter: workCenter.id,
                    })}
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ processing }) => (
                        <DialogFooter className="gap-2">
                            <DialogClose asChild>
                                <Button variant="outline">Cancelar</Button>
                            </DialogClose>
                            <Button
                                type="submit"
                                variant="destructive"
                                disabled={processing}
                            >
                                {processing && <Spinner />}
                                Archivar
                            </Button>
                        </DialogFooter>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
