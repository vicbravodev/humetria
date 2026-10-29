import WorkCenterRowActions from '@/components/tenancy/work-center-row-actions';
import { TableCell, TableRow } from '@/components/ui/table';
import type { MexicanStateOption, WorkCenterRow } from '@/types';

export default function WorkCenterRowView({
    tenantSlug,
    workCenter,
    states,
}: {
    tenantSlug: string;
    workCenter: WorkCenterRow;
    states: MexicanStateOption[];
}) {
    return (
        <TableRow>
            <TableCell className="whitespace-normal">
                <p className="font-medium">{workCenter.name}</p>
                <p className="text-sm text-muted-foreground">
                    {workCenter.main_activity}
                </p>
            </TableCell>
            <TableCell className="min-w-64 text-sm whitespace-normal">
                {workCenter.address}
            </TableCell>
            {workCenter.archived_on !== null && (
                <TableCell className="text-sm text-muted-foreground">
                    {workCenter.archived_on}
                </TableCell>
            )}
            <TableCell className="w-px text-right">
                <WorkCenterRowActions
                    tenantSlug={tenantSlug}
                    workCenter={workCenter}
                    states={states}
                />
            </TableCell>
        </TableRow>
    );
}
