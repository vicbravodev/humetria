import { Link } from '@inertiajs/react';
import { CalendarCheck, CalendarDays } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { monthWord } from '@/lib/leave/calendar';
import { index as requests } from '@/routes/tenant/vacations/requests';

/** The calendar with nobody to show: a month with no absences (with the way to the requests) or filters that match nobody. */
export default function CalendarEmpty({
    month,
    showAll,
}: {
    month: string;
    showAll: boolean;
}) {
    const tenantSlug = useTenantSlug();

    if (showAll) {
        return (
            <EmptyState icon={CalendarDays} title="Nadie por mostrar">
                Ninguna persona coincide con el centro y el área elegidos.
            </EmptyState>
        );
    }

    return (
        <EmptyState
            icon={CalendarCheck}
            title={`Nadie tiene ausencias en ${monthWord(month)}`}
            action={
                <Button asChild variant="outline">
                    <Link href={requests.url(tenantSlug)}>
                        Ir a solicitudes
                    </Link>
                </Button>
            }
        >
            Cuando alguien tenga vacaciones o un permiso aprobado en el mes,
            aparecerá aquí.
        </EmptyState>
    );
}
