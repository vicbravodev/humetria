import { Link } from '@inertiajs/react';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import type { LiveItem } from '@/lib/route-links';

/** «Lo que sigue vivo mientras tanto» (board V3-AlDia): what keeps going once the route is done, each with its way in. */
export default function RouteLive({ items }: { items: LiveItem[] }) {
    const tenantSlug = useTenantSlug();

    return (
        <section aria-labelledby="alive" className="flex flex-col gap-3">
            <div>
                <h2 id="alive" className="text-xl leading-7 font-semibold">
                    Lo que sigue vivo mientras tanto
                </h2>
                <p className="text-sm text-muted-foreground">
                    Terminar la ruta no apaga la norma. Esto te lo recordamos
                    cuando toque.
                </p>
            </div>
            <ul className="@container overflow-hidden card">
                {items.map((item) => (
                    <li
                        key={item.title}
                        className="grid gap-x-4 gap-y-1 border-t px-5 py-4 first:border-t-0 @[480px]:grid-cols-[minmax(0,1fr)_auto] @[480px]:items-center"
                    >
                        <div className="min-w-0">
                            <p className="font-medium">{item.title}</p>
                            <p className="text-sm text-muted-foreground">
                                {item.text}
                            </p>
                        </div>
                        {'label' in item.action ? (
                            <Link
                                href={item.action.href(tenantSlug)}
                                className="touch-hit text-sm font-medium text-primary"
                            >
                                {item.action.label}
                            </Link>
                        ) : (
                            <span className="text-sm text-muted-foreground">
                                {item.action.note}
                            </span>
                        )}
                    </li>
                ))}
            </ul>
        </section>
    );
}
