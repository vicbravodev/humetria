import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import Initials from '@/components/humetria/initials';
import { Badge } from '@/components/ui/badge';
import { tenantStatusVariant } from '@/lib/tenant-status';
import { dashboard } from '@/routes/tenant';
import type { CompanyLink } from '@/types';

/** A company the person can open, as the console lists them; a blocked one opens too, to say why it is paused. */
export default function CompanyCard({ company }: { company: CompanyLink }) {
    return (
        <Link
            href={dashboard(company.slug)}
            className="flex min-h-[72px] items-center gap-3 rounded-xl border bg-card px-4 py-3 transition-colors duration-150 ease-out hover:border-input focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
        >
            <Initials
                name={company.trade_name}
                kind="company"
                className="size-10 text-sm"
            />
            <span className="flex min-w-0 grow flex-col items-start gap-1">
                <span className="truncate font-medium">
                    {company.trade_name}
                </span>
                <Badge variant={tenantStatusVariant(company.status)}>
                    {company.status_label}
                </Badge>
            </span>
            <ChevronRight
                aria-hidden
                className="size-5 shrink-0 text-muted-foreground"
            />
        </Link>
    );
}
