import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import Initials from '@/components/humetria/initials';
import { Badge } from '@/components/ui/badge';
import { tenantStatusVariant } from '@/lib/tenant-status';
import { dashboard } from '@/routes/tenant';
import type { CompanyLink } from '@/types';

/**
 * A company the person can open, as a row of its list (rediseño v2, the pattern of the panel's rows); a blocked one
 * opens too, to say why it is paused. When the card is narrow its status goes under the name.
 */
export default function CompanyRow({ company }: { company: CompanyLink }) {
    return (
        <li className="border-t first:border-t-0">
            <Link
                href={dashboard(company.slug)}
                className="flex min-h-16 items-center gap-3.5 px-5 py-3 transition-colors duration-150 ease-out hover:bg-muted focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-ring"
            >
                <Initials
                    name={company.trade_name}
                    kind="company"
                    className="size-10 text-sm"
                />
                <span className="flex min-w-0 grow flex-col items-start gap-1 @[480px]:flex-row @[480px]:items-center @[480px]:justify-between @[480px]:gap-4">
                    <span className="max-w-full truncate font-medium">
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
        </li>
    );
}
