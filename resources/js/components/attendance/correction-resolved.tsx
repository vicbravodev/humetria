import { Link } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { correctionsQuery } from '@/lib/attendance/corrections';
import { firstName } from '@/lib/attendance/correction-effects';
import { index } from '@/routes/tenant/attendance/corrections';
import type { CorrectionDetail } from '@/types';

/** How a request ended, with RH's note, and the way on: the next one waiting, or the approved ones when none is. */
export default function CorrectionResolved({
    detail,
    pending,
    q,
}: {
    detail: CorrectionDetail;
    /** How many requests still wait. */
    pending: number;
    q: string;
}) {
    const tenantSlug = useTenantSlug();
    const approved = detail.status === 'approved';
    const first = firstName(detail.person.name);

    return (
        <div
            role="status"
            className="flex flex-wrap items-center gap-4 rounded-xl bg-accent px-5 py-[18px]"
        >
            <ShieldCheck aria-hidden className="size-6 shrink-0 text-primary" />
            <p className="min-w-56 flex-1 text-pretty">
                <strong className="font-semibold">
                    {approved
                        ? 'Corrección aprobada.'
                        : 'Corrección rechazada.'}
                </strong>{' '}
                {approved
                    ? `La marca quedó en el registro de ${first} y su semana se reabre si estaba confirmada.`
                    : `${first} verá tu nota y podrá pedirla de nuevo.`}
                {detail.note !== null && (
                    <span className="mt-1 block text-muted-foreground">
                        “{detail.note}”
                    </span>
                )}
            </p>
            <Button asChild variant="outline">
                <Link
                    href={index(tenantSlug, {
                        query: correctionsQuery({
                            q,
                            status: pending > 0 ? 'pending' : 'approved',
                        }),
                    })}
                >
                    {pending > 0 ? 'Pasar a la siguiente' : 'Ver aprobadas'}
                </Link>
            </Button>
        </div>
    );
}
