import { Lock } from 'lucide-react';
import GroupMapCell from '@/components/engagement/results/group-map-cell';
import {
    Table,
    TableBody,
    TableCaption,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { EngagementDriver, EngagementGroup } from '@/types';

/**
 * Groups × themes: the whole company first, then each group the minimum lets show, and one «Protegido» row when any
 * was hidden. That row stands for all of them, so it says neither how many nor why.
 */
export default function GroupMapTable({
    columns,
    groups,
    hasHidden,
}: {
    columns: EngagementDriver[];
    groups: EngagementGroup[];
    hasHidden: boolean;
}) {
    const scoreOf = (group: EngagementGroup, key: string): number | null =>
        group.drivers.find((driver) => driver.key === key)?.score ?? null;

    return (
        <div className="overflow-x-auto card p-2 sm:p-3">
            <Table className="min-w-[640px] table-fixed">
                <TableCaption className="sr-only">
                    Respuestas favorables por grupo y tema
                </TableCaption>
                <TableHeader className="[&_tr]:border-0">
                    <TableRow className="hover:bg-transparent">
                        <TableHead className="w-[180px] bg-transparent">
                            <span className="sr-only">Grupo</span>
                        </TableHead>
                        {columns.map((column) => (
                            <TableHead
                                key={column.key}
                                className="bg-transparent text-center whitespace-normal"
                            >
                                {column.label}
                            </TableHead>
                        ))}
                    </TableRow>
                </TableHeader>
                <TableBody className="[&_tr]:border-0">
                    <TableRow className="hover:bg-transparent">
                        <TableCell className="font-medium">
                            Toda la empresa
                        </TableCell>
                        {columns.map((column) => (
                            <GroupMapCell
                                key={column.key}
                                score={column.score}
                                label={`Toda la empresa · ${column.label}`}
                            />
                        ))}
                    </TableRow>
                    {groups.map((group) => (
                        <TableRow
                            key={group.key}
                            className="hover:bg-transparent"
                        >
                            <TableCell className="text-sm">
                                {group.name}
                            </TableCell>
                            {columns.map((column) => (
                                <GroupMapCell
                                    key={column.key}
                                    score={scoreOf(group, column.key)}
                                    label={`${group.name} · ${column.label}`}
                                />
                            ))}
                        </TableRow>
                    ))}
                    {hasHidden && (
                        <TableRow className="hover:bg-transparent">
                            <TableCell className="text-sm text-muted-foreground">
                                <span className="inline-flex items-center gap-1.5">
                                    <Lock aria-hidden className="size-4" />
                                    Protegido
                                </span>
                            </TableCell>
                            {columns.map((column) => (
                                <GroupMapCell
                                    key={column.key}
                                    score={null}
                                    label={`Protegido · ${column.label}`}
                                />
                            ))}
                        </TableRow>
                    )}
                </TableBody>
            </Table>
        </div>
    );
}
