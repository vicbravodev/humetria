import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import Initials from '@/components/humetria/initials';
import StackedTable from '@/components/humetria/stacked-table';
import {
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { ManagerRow } from '@/types';
import { useEngagementRoutes } from '@/hooks/use-engagement-routes';

/**
 * The managers whose group of people is shown, by name: never ordered by result and with no figure beside the name, so
 * the list ranks nobody. Each opens the feedback of their group.
 */
export default function ManagerTable({
    tenantSlug,
    surveyId,
    managers,
}: {
    tenantSlug: string;
    surveyId: string;
    managers: ManagerRow[];
}) {
    const routes = useEngagementRoutes();

    return (
        <section className="overflow-hidden card">
            <StackedTable>
                <TableHeader className="bg-muted">
                    <TableRow>
                        <TableHead>Jefe</TableHead>
                        <TableHead className="text-right">
                            <span className="sr-only">Retroalimentación</span>
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {managers.map((manager) => (
                        <TableRow key={manager.id}>
                            <TableCell className="max-md:w-full">
                                <span className="flex items-center gap-3 font-medium">
                                    <Initials name={manager.name} />
                                    {manager.name}
                                </span>
                            </TableCell>
                            <TableCell className="text-right">
                                <Link
                                    href={routes.managers.show.url([
                                        tenantSlug,
                                        surveyId,
                                        manager.id,
                                    ])}
                                    className="touch-hit inline-flex items-center gap-1 text-sm font-medium text-primary hover:underline"
                                >
                                    Ver retroalimentación
                                    <ChevronRight
                                        aria-hidden
                                        className="size-4"
                                    />
                                </Link>
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </StackedTable>
        </section>
    );
}
