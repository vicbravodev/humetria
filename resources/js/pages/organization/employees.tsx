import { Head, Link, usePage } from '@inertiajs/react';
import ConsolePagination from '@/components/console-pagination';
import CreateEmployeeDialog from '@/components/organization/create-employee-dialog';
import EmployeesEmpty from '@/components/organization/employees-empty';
import EmployeesTable from '@/components/organization/employees-table';
import EmployeesToolbar from '@/components/organization/employees-toolbar';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { employeeQuery } from '@/lib/employees';
import { options as areaOptions } from '@/routes/tenant/areas';
import { create as importPage } from '@/routes/tenant/employees/import';
import { options as workCenterOptions } from '@/routes/tenant/work-centers';
import type {
    ChoiceOption,
    EmployeeFilters,
    EmployeeRow,
    Paginated,
} from '@/types';

export default function Employees({
    employees,
    filters,
    genders,
}: {
    employees: Paginated<EmployeeRow>;
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
    const filtered = Object.keys(employeeQuery(filters)).length > 0;

    return (
        <>
            <Head title="Empleados" />
            <div className="flex flex-col gap-8 px-4 py-6 md:px-10 md:py-8">
                <PageHeader title="Empleados">
                    <div className="flex flex-wrap items-center gap-3">
                        <p className="text-muted-foreground">
                            {employees.total === 1
                                ? '1 persona'
                                : `${employees.total} personas`}
                        </p>
                        <Button variant="outline" asChild>
                            <Link href={importPage(tenantSlug)}>
                                Cargar CSV
                            </Link>
                        </Button>
                        <CreateEmployeeDialog
                            tenantSlug={tenantSlug}
                            choices={choices}
                        />
                    </div>
                </PageHeader>
                <EmployeesToolbar tenantSlug={tenantSlug} filters={filters} />
                {employees.data.length === 0 ? (
                    <EmployeesEmpty filtered={filtered} />
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
