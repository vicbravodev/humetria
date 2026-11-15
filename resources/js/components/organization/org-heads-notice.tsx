import { Link } from '@inertiajs/react';
import Notice from '@/components/humetria/notice';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { headsRibbon } from '@/lib/org-chart/canvas-labels';
import { quality } from '@/routes/tenant/org-chart';

/**
 * The one amber note of the chart: several people have nobody above them (normally there is one), and perhaps others
 * lead nobody and are not drawn. The way to fix it is the list, filtered to who has no manager.
 */
export default function OrgHeadsNotice({
    heads,
    unplaced,
}: {
    /** People at the top who lead people. */
    heads: number;
    /** People with no manager who lead nobody. */
    unplaced: number;
}) {
    const tenantSlug = useTenantSlug();

    return (
        <Notice
            action={
                <Link
                    href={quality(tenantSlug, {
                        query: { issue: 'no_manager' },
                    })}
                    className="text-meta font-semibold underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                >
                    Asígnales jefe
                </Link>
            }
        >
            {headsRibbon(heads, unplaced)}
        </Notice>
    );
}
