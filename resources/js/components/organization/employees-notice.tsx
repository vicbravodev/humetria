import { Link } from '@inertiajs/react';
import Notice from '@/components/humetria/notice';
import { Button } from '@/components/ui/button';
import { employeeQuery, withoutEmailText } from '@/lib/employees';
import { index } from '@/routes/tenant/employees';
import type { EmployeeFilters } from '@/types';

/** The active people no survey link reaches, since the links go out by email; the action shows only them, or everyone again. */
export default function EmployeesNotice({
    tenantSlug,
    filters,
    count,
}: {
    tenantSlug: string;
    filters: EmployeeFilters;
    count: number;
}) {
    const text = withoutEmailText(count);
    const showing = filters.without_email;
    const href = index.url(tenantSlug, {
        query: employeeQuery({
            ...filters,
            status: 'active',
            without_email: !showing,
        }),
    });

    return (
        <Notice
            action={
                <Button
                    variant="outline"
                    size="sm"
                    asChild
                    className="border-warning-foreground/50 bg-transparent text-warning-foreground hover:bg-warning-foreground/10 hover:text-warning-foreground"
                >
                    <Link href={href} preserveScroll>
                        {showing ? 'Ver a todas' : text.action}
                    </Link>
                </Button>
            }
        >
            <strong>{text.fact}</strong> y no recibirán el enlace de sus
            encuestas, que hoy se envía por correo.
        </Notice>
    );
}
