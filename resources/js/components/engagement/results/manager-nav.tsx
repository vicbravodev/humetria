import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useEngagementRoutes } from '@/hooks/use-engagement-routes';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import type { ManagerRow } from '@/types';

/** «Jefe anterior / siguiente» by name, among the shown ones: a way through them without the list, never a ranking. */
export default function ManagerNav({
    surveyId,
    previous,
    next,
}: {
    surveyId: string;
    previous: ManagerRow | null;
    next: ManagerRow | null;
}) {
    const tenantSlug = useTenantSlug();
    const routes = useEngagementRoutes();
    const href = (manager: ManagerRow) =>
        routes.managers.show.url([tenantSlug, surveyId, manager.id]);

    return (
        <nav aria-label="Otros jefes" className="flex flex-wrap gap-2">
            {previous !== null && (
                <Button asChild variant="outline">
                    <Link href={href(previous)}>
                        <ChevronLeft aria-hidden />
                        {previous.name}
                    </Link>
                </Button>
            )}
            {next !== null && (
                <Button asChild variant="outline">
                    <Link href={href(next)}>
                        {next.name}
                        <ChevronRight aria-hidden />
                    </Link>
                </Button>
            )}
        </nav>
    );
}
