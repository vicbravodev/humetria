import { Link } from '@inertiajs/react';
import { Inbox, SearchX } from 'lucide-react';
import EmptyState from '@/components/humetria/empty-state';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { emptyQueue } from '@/lib/leave/queue';
import { index } from '@/routes/tenant/vacations/requests';
import type { LeaveQueueFilters, LeaveQueueTab } from '@/types';

/**
 * What the queue shows when a tab has no rows: with a search or filters, that nothing matched and the way to remove them;
 * without, what it takes for something to appear, and from «Por atender» the way to what waits for the managers.
 */
export default function RequestsEmpty({
    tab,
    filters,
    waiting,
}: {
    tab: LeaveQueueTab;
    filters: Omit<LeaveQueueFilters, 'tab'>;
    waiting: number;
}) {
    const tenantSlug = useTenantSlug();
    const { title, text } = emptyQueue(tab);
    const narrowed =
        filters.q.trim() !== '' ||
        filters.center !== null ||
        filters.area !== null;

    if (narrowed) {
        return (
            <EmptyState
                icon={SearchX}
                title="Ninguna solicitud coincide"
                action={
                    <Button asChild variant="outline">
                        <Link
                            href={index.url(tenantSlug, {
                                query: tab === 'attend' ? {} : { tab },
                            })}
                        >
                            Quitar filtros
                        </Link>
                    </Button>
                }
            >
                Prueba con otro nombre, otro centro o otra área.
            </EmptyState>
        );
    }

    return (
        <EmptyState
            icon={Inbox}
            title={title}
            action={
                tab === 'attend' && waiting > 0 ? (
                    <Button asChild variant="outline">
                        <Link
                            href={index.url(tenantSlug, {
                                query: { tab: 'waiting' },
                            })}
                        >
                            Ver las que esperan a su jefe ({waiting})
                        </Link>
                    </Button>
                ) : undefined
            }
        >
            {text}
        </EmptyState>
    );
}
