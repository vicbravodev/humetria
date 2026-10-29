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
import { destroy } from '@/routes/tenant/members';
import type { MemberRow } from '@/types';

/** Asks for confirmation before taking away someone's access. Their account stays. */
export default function RemoveMemberDialog({
    tenantSlug,
    member,
    open,
    onOpenChange,
}: {
    tenantSlug: string;
    member: MemberRow;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogTitle>¿Quitar el acceso de {member.name}?</DialogTitle>
                <DialogDescription>
                    Ya no podrá entrar a esta empresa. Su cuenta sigue
                    existiendo y puedes invitarle de nuevo cuando quieras.
                </DialogDescription>
                <Form
                    {...destroy.form({ tenant: tenantSlug, member: member.id })}
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
                                Quitar acceso
                            </Button>
                        </DialogFooter>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
