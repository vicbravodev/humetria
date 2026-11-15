import { Head, setLayoutProps } from '@inertiajs/react';
import { Briefcase, CalendarCheck, FileText } from 'lucide-react';
import { useState } from 'react';
import PageHeader from '@/components/humetria/page-header';
import AdjustDialog from '@/components/leave/adjust-dialog';
import RecordActions from '@/components/leave/record-actions';
import RecordBody from '@/components/leave/record-body';
import RecordCurrent from '@/components/leave/record-current';
import TerminationCard from '@/components/leave/termination-card';
import { Badge } from '@/components/ui/badge';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { dateSpan } from '@/lib/leave/format';
import { leaveTrail } from '@/lib/leave/trail';
import { index as balances } from '@/routes/tenant/vacations/balances';
import type {
    LeaveLedgerEntry,
    LeaveRecordPeriod,
    LeaveRecordPerson,
    LeaveRecordRequest,
    LeaveRecordStatement,
    LeaveRecordTermination,
    LeaveTypeOption,
    PageFact,
    Paginated,
} from '@/types';

/**
 * The record of one person, to explain their days: the current period as an arch with its three dates, and under it their
 * movements (with the balance after each), requests, statements and periods. Days and dates only, never an amount.
 */
export default function LeaveEmployee({
    person,
    canManage,
    balance,
    types,
    periods,
    entries,
    requests,
    statements,
    termination,
}: {
    person: LeaveRecordPerson;
    canManage: boolean;
    balance: { available: number; advanced: number; waiting: number };
    types: LeaveTypeOption[];
    periods: LeaveRecordPeriod[];
    entries: Paginated<LeaveLedgerEntry>;
    requests: Paginated<LeaveRecordRequest>;
    statements: LeaveRecordStatement[];
    termination: LeaveRecordTermination | null;
}) {
    const tenantSlug = useTenantSlug();
    const [adjusting, setAdjusting] = useState<LeaveRecordPeriod | null>(null);
    const current = periods[0];
    const facts: PageFact[] = [
        { icon: Briefcase, text: `${person.jobTitle} · ${person.area}` },
        ...(person.startedOn === null
            ? []
            : [
                  {
                      icon: CalendarCheck,
                      text: `Ingreso: ${dateSpan(person.startedOn, person.startedOn)}`,
                  },
              ]),
        { icon: FileText, text: `Política: ${person.policy}` },
    ];

    setLayoutProps({
        breadcrumbs: leaveTrail(
            { title: 'Saldos', href: balances.url(tenantSlug) },
            person.name,
        ),
    });

    return (
        <>
            <Head title={`Vacaciones de ${person.name}`} />
            <div className="mx-auto flex w-full max-w-[1240px] flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <PageHeader
                    title={person.name}
                    status={
                        <Badge
                            variant={person.active ? 'success' : 'secondary'}
                        >
                            {person.active ? 'Activa' : 'Baja'}
                        </Badge>
                    }
                    facts={facts}
                >
                    {canManage && (
                        <RecordActions
                            person={person}
                            types={types}
                            current={current}
                            balance={balance}
                            onAdjust={setAdjusting}
                        />
                    )}
                </PageHeader>
                {canManage && (
                    <AdjustDialog
                        employeeId={person.id}
                        period={adjusting}
                        onClose={() => setAdjusting(null)}
                    />
                )}
                {termination !== null && (
                    <TerminationCard termination={termination} />
                )}
                <RecordCurrent
                    person={person}
                    current={current}
                    advanced={balance.advanced}
                    waiting={balance.waiting}
                    statements={statements}
                    canManage={canManage}
                />
                <RecordBody
                    canManage={canManage}
                    periods={periods}
                    entries={entries}
                    requests={requests}
                    statements={statements}
                    onAdjust={setAdjusting}
                />
            </div>
        </>
    );
}
