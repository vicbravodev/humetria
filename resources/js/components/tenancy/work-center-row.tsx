import WorkCenterRowActions from '@/components/tenancy/work-center-row-actions';
import { TableCell, TableRow } from '@/components/ui/table';
import { guidesText, staffText } from '@/lib/work-centers';
import type { MexicanStateOption, WorkCenterRow } from '@/types';

export default function WorkCenterRowView({
    tenantSlug,
    workCenter,
    states,
}: {
    tenantSlug: string;
    workCenter: WorkCenterRow;
    states: MexicanStateOption[] | null;
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
            <TableCell className="text-sm whitespace-nowrap">
                <p className="tabular-nums">
                    {staffText(workCenter.employees)}
                </p>
                {guidesText(workCenter.guides) !== null && (
                    <p className="text-muted-foreground">
                        {guidesText(workCenter.guides)}
                    </p>
                )}
            </TableCell>
            {workCenter.archived_on !== null && (
                <TableCell className="text-sm text-muted-foreground">
                    {workCenter.archived_on}
                </TableCell>
            )}
            {states !== null && (
                <TableCell className="w-px text-right">
                    <WorkCenterRowActions
                        tenantSlug={tenantSlug}
                        workCenter={workCenter}
                        states={states}
                    />
                </TableCell>
            )}
        </TableRow>
    );
}
