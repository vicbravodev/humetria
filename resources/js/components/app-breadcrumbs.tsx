import { Link, usePage } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { Fragment } from 'react';
import { useCurrentUrl } from '@/hooks/use-current-url';
import {
    complianceNavItems,
    mainNavItems,
    navTrail,
    superadminNavItems,
} from '@/lib/app-navigation';
import type { BreadcrumbItem } from '@/types';

/**
 * Where the page sits, in the top bar (pantallas: every panel page): "Autopartes Demo › Empleados",
 * "NOM-035 › Oficinas Monterrey". A page that sets none gets its place in the sidebar.
 */
export function AppBreadcrumbs({ items }: { items: BreadcrumbItem[] }) {
    const { auth, tenant } = usePage().props;
    const { currentUrl } = useCurrentUrl();
    const trail =
        items.length > 0
            ? items
            : navTrail(
                  [
                      {
                          label: tenant?.trade_name ?? 'Humetría',
                          items: mainNavItems(tenant, auth.isSuperadmin),
                      },
                      {
                          label: 'Cumplimiento',
                          items: complianceNavItems(tenant),
                      },
                      {
                          label: 'Plataforma',
                          items: auth.isSuperadmin ? superadminNavItems : [],
                      },
                  ],
                  currentUrl,
              );

    if (trail.length === 0) {
        return null;
    }

    return (
        <nav aria-label="Ruta" className="min-w-0">
            <ol className="flex min-w-0 items-center gap-1.5 text-[15px] leading-[22px]">
                {trail.map((crumb, index) => (
                    <Fragment key={`${crumb.title}-${index}`}>
                        {index > 0 && (
                            <ChevronRight
                                aria-hidden
                                className="size-4 shrink-0 text-muted-foreground"
                            />
                        )}
                        <li
                            className={
                                index === trail.length - 1
                                    ? 'max-w-full shrink-0 truncate font-medium'
                                    : 'hidden min-w-0 truncate text-muted-foreground sm:block'
                            }
                            aria-current={
                                index === trail.length - 1 ? 'page' : undefined
                            }
                        >
                            {crumb.href !== undefined &&
                            index < trail.length - 1 ? (
                                <Link
                                    href={crumb.href}
                                    className="rounded-sm transition-colors hover:text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                                >
                                    {crumb.title}
                                </Link>
                            ) : (
                                crumb.title
                            )}
                        </li>
                    </Fragment>
                ))}
            </ol>
        </nav>
    );
}
