import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { ArrowLeft, CircleCheck, CircleDashed } from 'lucide-react';
import PrintButton from '@/components/humetria/print-button';
import StatementSheet from '@/components/leave/statement-sheet';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { leaveTrail } from '@/lib/leave/trail';
import { show } from '@/routes/tenant/vacations/employees';
import type { StatementSheetData } from '@/types';

/** The annual statement of a person as RH prints it, with whether the person confirmed they received it. */
export default function LeaveStatement({
    employeeId,
    sheet,
}: {
    employeeId: string;
    sheet: StatementSheetData;
}) {
    const tenantSlug = useTenantSlug();
    setLayoutProps({
        breadcrumbs: leaveTrail(sheet.name, 'Constancia'),
    });

    return (
        <>
            <Head title={`Constancia de vacaciones · ${sheet.name}`} />
            <div className="mx-auto flex w-full max-w-[880px] flex-col gap-6 px-4 py-6 md:px-6 md:py-8 print:max-w-none print:p-0">
                <div className="flex flex-wrap items-center justify-between gap-3 print:hidden">
                    <Button variant="outline" asChild>
                        <Link
                            href={show.url({
                                tenant: tenantSlug,
                                employee: employeeId,
                            })}
                        >
                            <ArrowLeft aria-hidden />
                            Volver al expediente
                        </Link>
                    </Button>
                    <PrintButton />
                </div>
                <StatementSheet sheet={sheet}>
                    <p className="flex items-center gap-2 font-medium text-primary">
                        {sheet.acknowledgedOn === null ? (
                            <>
                                <CircleDashed aria-hidden className="size-5" />
                                Sin acuse
                            </>
                        ) : (
                            <>
                                <CircleCheck aria-hidden className="size-5" />
                                Recibida por la persona el{' '}
                                {sheet.acknowledgedOn}
                            </>
                        )}
                    </p>
                </StatementSheet>
            </div>
        </>
    );
}
