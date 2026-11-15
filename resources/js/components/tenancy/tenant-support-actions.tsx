import { Link, router } from '@inertiajs/react';
import { LogIn } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { impersonate } from '@/routes/companies';
import type { TenantRow } from '@/types';

/** What support does with a company: enter as its owner, when it has one. It changes nothing about the company. */
export default function TenantSupportActions({
    tenant,
}: {
    tenant: TenantRow;
}) {
    if (tenant.owner === null) {
        return null;
    }

    return (
        <Button asChild variant="ghost" size="sm">
            {/* Another person's session: nothing prefetched as the staff member carries over. */}
            <Link
                href={impersonate(tenant.id)}
                as="button"
                onClick={() => router.flushAll()}
                aria-label={`Entrar como owner de ${tenant.trade_name}`}
            >
                <LogIn />
                Entrar como owner
            </Link>
        </Button>
    );
}
