import { Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Card, CardHeader, CardTitle } from '@/components/ui/card';
import { tenantStatusVariant } from '@/lib/tenant-status';
import { dashboard } from '@/routes/tenant';
import type { CompanyLink } from '@/types';

/** A company the person can open; a blocked one opens too, to say why it is paused. */
export default function CompanyCard({ company }: { company: CompanyLink }) {
    return (
        <Card className="transition-colors focus-within:border-ring hover:border-primary">
            <CardHeader className="flex-row items-center justify-between gap-4">
                <CardTitle className="text-lg">
                    <Link
                        href={dashboard(company.slug)}
                        className="after:absolute after:inset-0 focus-visible:outline-none"
                    >
                        {company.trade_name}
                    </Link>
                </CardTitle>
                <Badge variant={tenantStatusVariant(company.status)}>
                    {company.status_label}
                </Badge>
            </CardHeader>
        </Card>
    );
}
