import { Link } from '@inertiajs/react';
import { TriangleAlert } from 'lucide-react';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { leftOutText } from '@/lib/compensation/scenario-draft';
import { index as tabulator } from '@/routes/tenant/compensation/tabulator';

/** Who the cost leaves out, with «Resolver», which opens the data of the contract where each gap is named. */
export default function LeftOutNotice({ people }: { people: number }) {
    const tenantSlug = useTenantSlug();

    return (
        <p className="flex gap-2.5 rounded-xl bg-warning px-4 py-3 text-sm text-warning-foreground">
            <TriangleAlert aria-hidden className="mt-0.5 size-4 shrink-0" />
            <span>
                {leftOutText(people)}{' '}
                <Link
                    href={tabulator(tenantSlug)}
                    className="font-medium underline underline-offset-2"
                >
                    Resolver
                </Link>
            </span>
        </p>
    );
}
