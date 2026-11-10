import { Head, setLayoutProps } from '@inertiajs/react';
import CountTabs from '@/components/humetria/count-tabs';
import PageHeader from '@/components/humetria/page-header';
import ReadOnlyNotice from '@/components/humetria/read-only-notice';
import HolidaysTable from '@/components/leave/holidays-table';
import TypesTable from '@/components/leave/types-table';
import WeekCard from '@/components/leave/week-card';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { show } from '@/routes/tenant/vacations/settings';
import type {
    LeaveHolidayRow,
    LeaveSettingData,
    LeaveSettingsTab,
    LeaveTypeRow,
    LeaveWorkWeek,
    Paginated,
} from '@/types';

/**
 * What RH adjusts of Vacaciones: the leave types, the holidays of the company and the working week of the law. A viewer
 * reads it all.
 */
export default function LeaveSettings({
    tab,
    counts,
    types,
    holidays,
    setting,
    workWeeks,
    canManage,
}: {
    tab: LeaveSettingsTab;
    counts: { types: number; holidays: number };
    types: Paginated<LeaveTypeRow>;
    holidays: Paginated<LeaveHolidayRow>;
    setting: LeaveSettingData;
    /** The two working weeks the law's policy can count by. */
    workWeeks: { value: LeaveWorkWeek; label: string }[];
    canManage: boolean;
}) {
    const tenantSlug = useTenantSlug();
    const link = (next: LeaveSettingsTab) =>
        show.url(tenantSlug, { query: { tab: next } });

    setLayoutProps({
        breadcrumbs: [{ title: 'Vacaciones' }, { title: 'Configuración' }],
    });

    return (
        <>
            <Head title="Configuración de vacaciones" />
            <div className="mx-auto flex w-full max-w-[1240px] flex-col gap-6 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <PageHeader
                    caption="Vacaciones"
                    title="Configuración"
                    description="Los permisos sin saldo, los días que la empresa no trabaja y la semana laboral con que se cuentan los días."
                />
                {!canManage && <ReadOnlyNotice />}
                <div>
                    <CountTabs
                        label="Secciones de la configuración"
                        tabs={[
                            {
                                label: 'Tipos de permiso',
                                count: counts.types,
                                href: link('types'),
                                active: tab === 'types',
                            },
                            {
                                label: 'Días festivos',
                                count: counts.holidays,
                                href: link('holidays'),
                                active: tab === 'holidays',
                            },
                            {
                                label: 'Semana y arranque',
                                href: link('week'),
                                active: tab === 'week',
                            },
                        ]}
                    />
                </div>
                {tab === 'types' && (
                    <TypesTable types={types} canManage={canManage} />
                )}
                {tab === 'holidays' && (
                    <HolidaysTable holidays={holidays} canManage={canManage} />
                )}
                {tab === 'week' && (
                    <WeekCard
                        setting={setting}
                        workWeeks={workWeeks}
                        canManage={canManage}
                    />
                )}
            </div>
        </>
    );
}
