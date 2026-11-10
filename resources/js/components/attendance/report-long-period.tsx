import { Link } from '@inertiajs/react';
import { CalendarRange, FileDown } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { reportQuery, shortenedPeriod } from '@/lib/attendance/report';
import { report } from '@/routes/tenant/attendance';

/** A period too long for the screen: why there is no table, and its CSV to download. */
export default function ReportLongPeriod({
    days,
    maxOnScreen,
    exportUrl,
    center,
    from,
}: {
    days: number;
    maxOnScreen: number;
    exportUrl: string;
    center: string;
    from: string;
}) {
    const tenantSlug = useTenantSlug();
    const shorter = report.url(tenantSlug, {
        query: reportQuery({
            center,
            from,
            to: shortenedPeriod(from, maxOnScreen),
            q: '',
        }),
    });

    return (
        <EmptyState
            icon={CalendarRange}
            title="Este periodo se descarga en CSV"
            action={
                <div className="flex flex-wrap justify-center gap-2.5">
                    <Button asChild variant="outline">
                        <Link href={shorter}>Acortar a {maxOnScreen} días</Link>
                    </Button>
                    <Button asChild>
                        <a href={exportUrl} download>
                            <FileDown aria-hidden />
                            Descargar CSV
                        </a>
                    </Button>
                </div>
            }
        >
            {`Elegiste ${days} días y en pantalla mostramos hasta ${maxOnScreen}. Descarga el CSV con cada día de cada persona del periodo, o elige uno más corto para verlo aquí.`}
        </EmptyState>
    );
}
