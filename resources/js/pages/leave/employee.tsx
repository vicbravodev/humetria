import { Head, setLayoutProps } from '@inertiajs/react';
import { Briefcase, CalendarCheck, MapPin } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';
import PageHeader from '@/components/humetria/page-header';
import LedgerTable from '@/components/leave/ledger-table';
import PeriodsSection from '@/components/leave/periods-section';
import RecordSection from '@/components/leave/record-section';
import RecordRequests from '@/components/leave/record-requests';
import RecordStatements from '@/components/leave/record-statements';
import RecordSummary from '@/components/leave/record-summary';
import TerminationCard from '@/components/leave/termination-card';
import { Badge } from '@/components/ui/badge';
import { dateSpan } from '@/lib/leave/format';
import type {
    LeaveLedgerEntry,
    LeaveRecordPeriod,
    LeaveRecordPerson,
    LeaveRecordRequest,
    LeaveRecordStatement,
    LeaveRecordTermination,
    PageFact,
    Paginated,
} from '@/types';

/**
 * The record of one person: their days by period, every movement of the ledger, their requests with who answered, their
 * annual statements and, if they left, what they are owed in days. Days and dates only, never an amount.
 */
export default function LeaveEmployee({
    person,
    canManage,
    balance,
    periods,
    entries,
    requests,
    statements,
    termination,
}: {
    person: LeaveRecordPerson;
    canManage: boolean;
    balance: { available: number; advanced: number };
    periods: LeaveRecordPeriod[];
    entries: Paginated<LeaveLedgerEntry>;
    requests: Paginated<LeaveRecordRequest>;
    statements: LeaveRecordStatement[];
    termination: LeaveRecordTermination | null;
}) {
    setLayoutProps({
        breadcrumbs: [
            { title: 'Vacaciones' },
            { title: 'Saldos' },
            { title: person.name },
        ],
    });
    const facts: PageFact[] = [
        { icon: Briefcase, text: person.jobTitle },
        { icon: MapPin, text: person.area },
        ...(person.startedOn === null
            ? []
            : [
                  {
                      icon: CalendarCheck,
                      text: `Ingreso: ${dateSpan(person.startedOn, person.startedOn)}`,
                  },
              ]),
    ];

    return (
        <>
            <Head title={`Vacaciones de ${person.name}`} />
            <div className="mx-auto flex w-full max-w-[1240px] flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <PageHeader
                    caption="Vacaciones"
                    title={person.name}
                    status={
                        <Badge
                            variant={person.active ? 'success' : 'secondary'}
                        >
                            {person.active ? 'Activa' : 'Baja'}
                        </Badge>
                    }
                    facts={facts}
                />
                <RecordSummary
                    available={balance.available}
                    advanced={balance.advanced}
                    policy={person.policy}
                />
                {termination !== null && (
                    <TerminationCard termination={termination} />
                )}
                <RecordSection title="Periodos">
                    {periods.length === 0 ? (
                        <EmptyState
                            icon={CalendarCheck}
                            title="Aún no tiene periodos"
                        >
                            El primero se otorga en su primer aniversario, o al
                            cargar sus saldos iniciales.
                        </EmptyState>
                    ) : (
                        <PeriodsSection
                            employeeId={person.id}
                            periods={periods}
                            canManage={canManage}
                        />
                    )}
                </RecordSection>
                <RecordSection title="Movimientos de sus días">
                    <LedgerTable entries={entries} />
                </RecordSection>
                <RecordSection title="Solicitudes">
                    <RecordRequests requests={requests} />
                </RecordSection>
                {statements.length > 0 && (
                    <RecordSection title="Constancias anuales">
                        <RecordStatements statements={statements} />
                    </RecordSection>
                )}
            </div>
        </>
    );
}
