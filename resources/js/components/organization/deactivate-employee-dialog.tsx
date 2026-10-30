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
import { deactivate } from '@/routes/tenant/employees';
import type { EmployeeRow } from '@/types';

/** Asks for confirmation before deactivating an employee. Nothing is deleted. */
export default function DeactivateEmployeeDialog({
    tenantSlug,
    employee,
    open,
    onOpenChange,
}: {
    tenantSlug: string;
    employee: EmployeeRow;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogTitle>¿Dar de baja a {employee.name}?</DialogTitle>
                <DialogDescription>
                    Ya no recibirá encuestas ni contará en su centro de trabajo.
                    Su historia se conserva y puedes reactivarle cuando quieras.
                </DialogDescription>
                <Form
                    {...deactivate.form({
                        tenant: tenantSlug,
                        employee: employee.id,
                    })}
                    options={{ preserveScroll: true }}
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
                                Dar de baja
                            </Button>
                        </DialogFooter>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
