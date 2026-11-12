import { Link } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import { List, Network } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { index, quality } from '@/routes/tenant/org-chart';
import { cn } from '@/lib/utils';

const LINK =
    'relative inline-flex h-[38px] items-center gap-2 rounded-lg px-3.5 text-sm font-medium whitespace-nowrap text-muted-foreground transition-colors duration-150 ease-out before:absolute before:inset-x-0 before:-inset-y-[3px] hover:text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring';

/** Organigrama | Lista: the same people drawn as a chart, or as the table that is also the accessible view. */
export default function OrgViewSwitch({
    current,
}: {
    current: 'chart' | 'list';
}) {
    const tenantSlug = useTenantSlug();
    const views: {
        key: 'chart' | 'list';
        label: string;
        icon: LucideIcon;
        href: NonNullable<InertiaLinkProps['href']>;
    }[] = [
        {
            key: 'chart',
            label: 'Organigrama',
            icon: Network,
            href: index(tenantSlug),
        },
        { key: 'list', label: 'Lista', icon: List, href: quality(tenantSlug) },
    ];

    return (
        <nav
            aria-label="Vista del organigrama"
            className="inline-flex max-w-full gap-0.5 rounded-[10px] border bg-muted p-[3px] print:hidden"
        >
            {views.map(({ key, label, icon: Icon, href }) => (
                <Link
                    key={key}
                    href={href}
                    aria-current={current === key ? 'page' : undefined}
                    className={cn(
                        LINK,
                        current === key &&
                            'bg-card text-foreground shadow-raised',
                    )}
                >
                    <Icon aria-hidden className="size-4" />
                    {label}
                </Link>
            ))}
        </nav>
    );
}
