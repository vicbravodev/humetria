import { Head, Link, usePage } from '@inertiajs/react';
import { Upload } from 'lucide-react';
import ConsolePagination from '@/components/console-pagination';
import CreateEmployeeSheet from '@/components/organization/create-employee-sheet';
import EmployeesEmpty from '@/components/organization/employees-empty';
import EmployeesNotice from '@/components/organization/employees-notice';
import EmployeesTable from '@/components/organization/employees-table';
import EmployeesToolbar from '@/components/organization/employees-toolbar';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { employeeQuery, headcountText } from '@/lib/employees';
import { options as areaOptions } from '@/routes/tenant/areas';
import { create as importPage } from '@/routes/tenant/employees/import';
import { options as workCenterOptions } from '@/routes/tenant/work-centers';
import type {
    ChoiceOption,
    EmployeeCounts,
    EmployeeFilters,
    EmployeeRow,
    Paginated,
} from '@/types';

export default function Employees({
    employees,
    counts,
    filters,
    genders,
}: {
    employees: Paginated<EmployeeRow>;
    counts: EmployeeCounts;
    filters: EmployeeFilters;
    genders: ChoiceOption[];
}) {
    // Always set here: this page only exists inside a company.
    const tenantSlug = usePage().props.tenant?.slug ?? '';
    const choices = {
        workCentersUrl: workCenterOptions.url(tenantSlug),
        areasUrl: areaOptions.url(tenantSlug),
        genders,
    };
    // The Bajas tab alone is not a search: it gets its own empty state.
    const filtered =
        Object.keys(employeeQuery({ ...filters, status: 'active' })).length > 0;

    return (
        <>
            <Head title="Empleados" />
            <div className="flex flex-col gap-8 px-4 py-6 md:px-10 md:py-8">
                <PageHeader
                    title="Empleados"
                    description={headcountText(counts)}
                >
                    <Button variant="outline" asChild>
                        <Link href={importPage(tenantSlug)}>
                            <Upload />
                            Cargar CSV
                        </Link>
                    </Button>
                    <CreateEmployeeSheet
                        tenantSlug={tenantSlug}
                        choices={choices}
                    />
                </PageHeader>
                {counts.without_email > 0 && (
                    <EmployeesNotice
                        tenantSlug={tenantSlug}
                        filters={filters}
                        count={counts.without_email}
                    />
                )}
                <EmployeesToolbar
                    tenantSlug={tenantSlug}
                    filters={filters}
                    counts={counts}
                />
                {employees.data.length === 0 ? (
                    <EmployeesEmpty
                        filtered={filtered}
                        inactive={filters.status === 'inactive'}
                    />
                ) : (
                    <EmployeesTable
                        tenantSlug={tenantSlug}
                        employees={employees.data}
                        choices={choices}
                    />
                )}
                <ConsolePagination
                    page={employees}
                    label="Páginas de empleados"
                    previous="Anteriores"
                    next="Siguientes"
                />
            </div>
        </>
    );
}
