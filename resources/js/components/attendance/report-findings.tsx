import { Link } from '@inertiajs/react';
import { CircleCheck, TriangleAlert } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { reportQuery } from '@/lib/attendance/report';
import { inspectionFindings } from '@/lib/attendance/report-findings';
import { report } from '@/routes/tenant/attendance';
import type { AttendanceReportProps } from '@/types';

/**
 * «Antes de mostrarlo en una inspección» (board A7): what makes the report look weak, each with the button that lists the
 * people to look at. With none, it says the report is ready. It is for RH, so it is not printed.
 */
export default function ReportFindings({
    summary,
    center,
    period,
}: Pick<AttendanceReportProps, 'center' | 'period'> & {
    summary: NonNullable<AttendanceReportProps['summary']>;
}) {
    const tenantSlug = useTenantSlug();
    const findings = inspectionFindings(summary);
    const listed = (show: 'flagged' | 'unconfirmed') =>
        report.url(tenantSlug, {
            query: reportQuery({
                center: center?.id ?? '',
                ...period,
                q: '',
                show,
            }),
        });

    if (findings.length === 0) {
        return (
            <p className="flex items-center gap-3 card px-5 py-4 text-muted-foreground print:hidden">
                <CircleCheck aria-hidden className="size-5 text-primary" />
                Nada que resolver antes de mostrarlo: todos los días tienen su
                fin y ninguno pasa de 12 horas.
            </p>
        );
    }

    return (
        <section
            aria-labelledby="antes"
            className="overflow-hidden card print:hidden"
        >
            <div className="px-5 pt-[18px] pb-3.5">
                <h2 id="antes" className="text-lg font-semibold">
                    Antes de mostrarlo en una inspección
                </h2>
                <p className="text-sm text-muted-foreground">
                    Esto hace que el reporte se vea débil. Revísalo aquí mismo.
                </p>
            </div>
            <ul className="divide-y border-t">
                {findings.map((finding) => (
                    <li
                        key={finding.key}
                        className="grid grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-4 px-5 py-3.5 max-sm:grid-cols-[auto_minmax(0,1fr)]"
                    >
                        <span
                            aria-hidden
                            className="flex size-9 items-center justify-center rounded-lg bg-warning text-warning-foreground"
                        >
                            <TriangleAlert className="size-5" />
                        </span>
                        <div className="flex min-w-0 flex-col">
                            <h3 className="font-medium text-balance">
                                {finding.title}
                            </h3>
                            <p className="text-sm text-pretty text-muted-foreground">
                                {finding.text}
                            </p>
                        </div>
                        <Button
                            asChild
                            variant="outline"
                            className="max-sm:col-span-full"
                        >
                            <Link href={listed(finding.show)} preserveScroll>
                                {finding.action}
                            </Link>
                        </Button>
                    </li>
                ))}
            </ul>
        </section>
    );
}
