import { Plus } from 'lucide-react';
import { useState } from 'react';
import ConfirmDialog from '@/components/humetria/confirm-dialog';
import EmptyState from '@/components/humetria/empty-state';
import Pagination from '@/components/humetria/pagination';
import StackedTable from '@/components/humetria/stacked-table';
import HolidayDialog from '@/components/leave/holiday-dialog';
import { Button } from '@/components/ui/button';
import {
    TableBody,
    TableCaption,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { describeDate } from '@/lib/calendar-date';
import { destroy } from '@/routes/tenant/vacations/holidays';
import type { LeaveHolidayRow, Paginated } from '@/types';
import { CalendarDays } from 'lucide-react';

/** The days the company does not work besides those of the law (LFT art. 74), newest first. */
export default function HolidaysTable({
    holidays,
    canManage,
}: {
    holidays: Paginated<LeaveHolidayRow>;
    canManage: boolean;
}) {
    const tenantSlug = useTenantSlug();
    const [adding, setAdding] = useState(false);

    return (
        <section aria-label="Días festivos" className="grid gap-4">
            {canManage && (
                <div className="flex justify-end">
                    <Button onClick={() => setAdding(true)}>
                        <Plus />
                        Nuevo día festivo
                    </Button>
                    <HolidayDialog open={adding} onOpenChange={setAdding} />
                </div>
            )}
            {holidays.total === 0 ? (
                <EmptyState icon={CalendarDays} title="Sin festivos propios">
                    Los descansos obligatorios de la ley ya se cuentan solos.
                    Agrega aquí los que la empresa da además, como el día de las
                    elecciones.
                </EmptyState>
            ) : (
                <StackedTable>
                    <TableCaption className="sr-only">
                        Días festivos de la empresa
                    </TableCaption>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Día</TableHead>
                            <TableHead>Nombre</TableHead>
                            <TableHead className="w-0">
                                <span className="sr-only">Acciones</span>
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {holidays.data.map((holiday) => (
                            <TableRow key={holiday.id}>
                                <TableCell className="font-medium whitespace-normal">
                                    {describeDate(holiday.date)}
                                </TableCell>
                                <TableCell className="whitespace-normal text-muted-foreground">
                                    {holiday.name}
                                </TableCell>
                                <TableCell className="text-right">
                                    {canManage && (
                                        <ConfirmDialog
                                            trigger={
                                                <Button variant="ghost">
                                                    Quitar
                                                </Button>
                                            }
                                            title={`¿Quitar ${holiday.name}?`}
                                            description="Las solicitudes ya hechas no cambian."
                                            confirmLabel="Quitar"
                                            form={destroy.form({
                                                tenant: tenantSlug,
                                                holiday: holiday.id,
                                            })}
                                            preserveScroll
                                        />
                                    )}
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </StackedTable>
            )}
            <Pagination
                page={holidays}
                label="Páginas de días festivos"
                pinned={false}
            />
        </section>
    );
}
