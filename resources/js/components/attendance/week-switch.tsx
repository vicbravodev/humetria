import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { overviewQuery } from '@/lib/attendance/overview';
import type { OverviewFilters } from '@/lib/attendance/overview';
import { index } from '@/routes/tenant/attendance';
import type { OverviewWeek } from '@/types';

/** The week before and the one after, keeping the center, the tab and the search: the next one only once it started. */
export default function WeekSwitch({
    week,
    filters,
}: {
    week: OverviewWeek;
    filters: Omit<OverviewFilters, 'week'>;
}) {
    const tenantSlug = useTenantSlug();
    const to = (start: string) =>
        index(tenantSlug, {
            query: overviewQuery({ ...filters, week: start }),
        });

    return (
        <nav aria-label="Otras semanas" className="flex gap-2 max-sm:w-full">
            <Button asChild variant="outline" className="max-sm:flex-1">
                <Link href={to(week.previous)} preserveScroll>
                    <ChevronLeft aria-hidden />
                    Semana anterior
                </Link>
            </Button>
            {week.next !== null && (
                <Button asChild variant="outline" className="max-sm:flex-1">
                    <Link href={to(week.next)} preserveScroll>
                        Semana siguiente
                        <ChevronRight aria-hidden />
                    </Link>
                </Button>
            )}
        </nav>
    );
}
