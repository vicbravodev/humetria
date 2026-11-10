import { Link } from '@inertiajs/react';
import { TrendingUp } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { trend } from '@/routes/tenant/pulse';

/** From the home of the pulse to how the company moves from survey to survey. */
export default function TrendLink() {
    const tenantSlug = useTenantSlug();

    return (
        <Button variant="outline" asChild>
            <Link href={trend(tenantSlug)}>
                <TrendingUp aria-hidden="true" />
                Ver tendencia
            </Link>
        </Button>
    );
}
