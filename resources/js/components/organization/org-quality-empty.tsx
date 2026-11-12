import { Link } from '@inertiajs/react';
import { CircleCheck } from 'lucide-react';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { orderText } from '@/lib/org-chart/quality';
import { index } from '@/routes/tenant/org-chart';

/**
 * Nothing to fix: everyone has a manager who is still here, with one person at the top and nobody leading too many.
 * A line of its own, not an empty page: the list below is still the way to change a manager.
 */
export default function OrgQualityEmpty({ people }: { people: number }) {
    const tenantSlug = useTenantSlug();

    return (
        <p
            role="status"
            className="flex flex-wrap items-center gap-x-2 gap-y-1 rounded-xl border bg-card px-5 py-3.5 text-meta"
        >
            <CircleCheck aria-hidden className="size-5 text-primary" />
            <span>
                <strong className="font-semibold">Todo en orden.</strong>{' '}
                {orderText(people)}
            </span>
            <Link
                href={index(tenantSlug)}
                className="inline-flex min-h-11 items-center font-semibold text-primary underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
            >
                Ver organigrama
            </Link>
        </p>
    );
}
