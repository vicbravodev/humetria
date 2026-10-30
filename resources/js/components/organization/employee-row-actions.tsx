import { router } from '@inertiajs/react';
import { Ellipsis, Pencil, UserCheck, UserMinus } from 'lucide-react';
import { useState } from 'react';
import DeactivateEmployeeDialog from '@/components/organization/deactivate-employee-dialog';
import EmployeeDialog from '@/components/organization/employee-dialog';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { reactivate, update } from '@/routes/tenant/employees';
import type { EmployeeChoices, EmployeeRow } from '@/types';

type Action = 'edit' | 'deactivate' | 'reactivate';

/** An active employee is edited or deactivated (apart, in the destructive color); a deactivated one is reactivated. */
export default function EmployeeRowActions({
    tenantSlug,
    employee,
    choices,
}: {
    tenantSlug: string;
    employee: EmployeeRow;
    choices: EmployeeChoices;
}) {
    const [open, setOpen] = useState<Action | null>(null);
    const dialog = (action: Action) => ({
        open: open === action,
        onOpenChange: (isOpen: boolean) => setOpen(isOpen ? action : null),
    });
    const ids = { tenant: tenantSlug, employee: employee.id };

    return (
        <>
            <DropdownMenu modal={false}>
                <DropdownMenuTrigger asChild>
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label={`Acciones de ${employee.name}`}
                        data-test="employee-actions"
                        className="text-muted-foreground hover:text-foreground data-[state=open]:bg-accent data-[state=open]:text-foreground"
                    >
                        <Ellipsis className="size-5" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    align="end"
                    className="min-w-44"
                    // Sent once the menu has closed: a page redrawn mid-animation leaves the menu unable to open again.
                    onCloseAutoFocus={() => {
                        if (open === 'reactivate') {
                            setOpen(null);
                            router.post(
                                reactivate.url(ids),
                                {},
                                { preserveScroll: true },
                            );
                        }
                    }}
                >
                    {employee.deactivated_on === null ? (
                        <>
                            <DropdownMenuItem onSelect={() => setOpen('edit')}>
                                <Pencil />
                                Editar
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                variant="destructive"
                                onSelect={() => setOpen('deactivate')}
                            >
                                <UserMinus />
                                Dar de baja
                            </DropdownMenuItem>
                        </>
                    ) : (
                        <DropdownMenuItem
                            onSelect={() => setOpen('reactivate')}
                        >
                            <UserCheck />
                            Reactivar
                        </DropdownMenuItem>
                    )}
                </DropdownMenuContent>
            </DropdownMenu>
            <EmployeeDialog
                {...dialog('edit')}
                copy={{
                    title: 'Editar empleado',
                    description: `Corrige los datos de ${employee.name}.`,
                    submit: 'Guardar',
                }}
                form={update.form(ids)}
                choices={choices}
                employee={employee}
            />
            <DeactivateEmployeeDialog
                {...dialog('deactivate')}
                tenantSlug={tenantSlug}
                employee={employee}
            />
        </>
    );
}
