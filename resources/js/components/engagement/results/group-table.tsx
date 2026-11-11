import GroupDetail from '@/components/engagement/results/group-detail';
import StackedTable from '@/components/humetria/stacked-table';
import {
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { favorableText, versusCompanyText } from '@/lib/engagement/format';
import type { EngagementGroup } from '@/types';

/**
 * The shown groups of a cut, each with its engagement index and how it stands against the company; one click opens what
 * the group answered. Only groups that passed the minimum are here, and nothing says how many did not.
 */
export default function GroupTable({
    heading,
    groups,
}: {
    heading: string;
    groups: EngagementGroup[];
}) {
    return (
        <section className="overflow-hidden card">
            <StackedTable>
                <TableHeader className="bg-muted">
                    <TableRow>
                        <TableHead>{heading}</TableHead>
                        <TableHead className="text-right">Respuestas</TableHead>
                        <TableHead className="text-right">Compromiso</TableHead>
                        <TableHead>Frente a la empresa</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {groups.map((group) => (
                        <TableRow key={group.key}>
                            <TableCell className="font-medium max-md:w-full">
                                {group.name}
                            </TableCell>
                            <TableCell className="text-right tabular-nums">
                                {group.responses} respuestas
                            </TableCell>
                            <TableCell className="text-right font-semibold tabular-nums">
                                {favorableText(group.index)}
                            </TableCell>
                            <TableCell className="text-muted-foreground">
                                {versusCompanyText(group.vsCompany)}
                            </TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </StackedTable>
            {groups.map((group) => (
                <details key={group.key} className="group border-t">
                    <summary className="touch-hit cursor-pointer list-none px-5 py-3 text-sm font-medium text-primary hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring [&::-webkit-details-marker]:hidden">
                        Ver lo que contestó {group.name}
                    </summary>
                    <GroupDetail group={group} />
                </details>
            ))}
        </section>
    );
}
