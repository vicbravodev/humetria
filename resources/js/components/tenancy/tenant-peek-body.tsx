import { Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { tenantStatusVariant } from '@/lib/tenant-status';
import { peekFacts } from '@/lib/tenant-work';
import { show } from '@/routes/companies';
import type { TenantRow } from '@/types';

/** What the console's quick view says of a company (tablero Empresas): where it stands, its plan, who owns it and who answers for it. */
export default function TenantPeekBody({ tenant }: { tenant: TenantRow }) {
    const facts = peekFacts(tenant);

    return (
        <div className="grid content-start gap-4">
            <Badge
                variant={tenantStatusVariant(tenant.status)}
                className="justify-self-start"
            >
                {tenant.status_label}
            </Badge>
            {tenant.restriction_reason !== null && (
                <p className="text-sm text-muted-foreground">
                    Motivo: {tenant.restriction_reason}
                </p>
            )}
            <dl className="grid gap-3 text-sm">
                {facts.map(([label, value]) => (
                    <div key={label} className="flex justify-between gap-4">
                        <dt className="text-muted-foreground">{label}</dt>
                        <dd className="text-right font-medium [overflow-wrap:anywhere]">
                            {value}
                        </dd>
                    </div>
                ))}
            </dl>
            <Button asChild>
                <Link href={show(tenant.id)}>Abrir la empresa</Link>
            </Button>
        </div>
    );
}
