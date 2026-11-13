import { Link } from '@inertiajs/react';
import { CalendarCheck } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';
import RecordHero from '@/components/leave/record-hero';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { openStatement } from '@/lib/leave/record';
import { index as employees } from '@/routes/tenant/employees';
import type {
    LeaveRecordPeriod,
    LeaveRecordPerson,
    LeaveRecordStatement,
} from '@/types';

/** The current period of the person as the protagonist; with none, why (no start date, or no anniversary yet) and the way on. */
export default function RecordCurrent({
    person,
    current,
    advanced,
    waiting,
    statements,
    canManage,
}: {
    person: LeaveRecordPerson;
    current: LeaveRecordPeriod | undefined;
    advanced: number;
    waiting: number;
    statements: LeaveRecordStatement[];
    canManage: boolean;
}) {
    const tenantSlug = useTenantSlug();

    if (current !== undefined) {
        return (
            <RecordHero
                period={current}
                advanced={advanced}
                waiting={waiting}
                open={openStatement(statements)}
                canManage={canManage}
                personId={person.id}
            />
        );
    }

    if (person.startedOn !== null) {
        return (
            <EmptyState icon={CalendarCheck} title="Aún no tiene periodos">
                El primero se otorga en su primer aniversario, o al cargar sus
                saldos iniciales.
            </EmptyState>
        );
    }

    return (
        <EmptyState
            icon={CalendarCheck}
            title="Falta su fecha de ingreso"
            action={
                <Button asChild variant="outline">
                    <Link
                        href={employees.url(tenantSlug, {
                            query: { q: person.name },
                        })}
                    >
                        Capturar la fecha
                    </Link>
                </Button>
            }
        >
            Sin ella no se le otorgan días de vacaciones.
        </EmptyState>
    );
}
