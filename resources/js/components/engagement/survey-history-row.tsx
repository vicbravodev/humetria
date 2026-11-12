import { Link } from '@inertiajs/react';
import BarMeter from '@/components/humetria/bar-meter';
import { Badge } from '@/components/ui/badge';
import { TableCell, TableRow } from '@/components/ui/table';
import { describeDate } from '@/lib/calendar-date';
import type { ClimateSurveyHistoryRow } from '@/types';

/**
 * A closed survey of the history: the whole row opens it; its mode (a pulse), the day it closed and its participation
 * as a bar against the average of the history.
 */
export default function SurveyHistoryRow({
    survey,
    href,
    withMode,
    average,
}: {
    survey: ClimateSurveyHistoryRow;
    href: string;
    withMode: boolean;
    average: number | null;
}) {
    const closed =
        survey.closedOn === null
            ? '—'
            : (describeDate(survey.closedOn) ?? survey.closedOn);

    return (
        <TableRow className="relative hover:bg-muted/50 md:h-16">
            <TableCell className="font-medium max-md:w-full">
                <Link
                    href={href}
                    className="flex min-h-11 items-center outline-none before:absolute before:inset-0 before:rounded-md focus-visible:before:ring-2 focus-visible:before:ring-ring"
                >
                    {survey.name}
                </Link>
            </TableCell>
            {withMode && (
                <TableCell>
                    {survey.mode !== null && (
                        <Badge variant="outline">{survey.mode}</Badge>
                    )}
                </TableCell>
            )}
            <TableCell className="text-muted-foreground md:text-foreground">
                <span className="md:hidden">Cerró el </span>
                {closed}
            </TableCell>
            <TableCell className="max-md:w-full md:w-[260px]">
                {survey.invited === null || survey.rate === null ? (
                    '—'
                ) : (
                    <span className="flex flex-col gap-1.5">
                        <span className="flex items-baseline justify-between gap-3 tabular-nums">
                            <span className="font-medium">{survey.rate} %</span>
                            <span className="text-sm text-muted-foreground">
                                {survey.answered} de {survey.invited}
                            </span>
                        </span>
                        <BarMeter
                            percent={survey.rate}
                            tooltip={`${survey.name}: ${survey.rate} % contestó`}
                            {...(average === null
                                ? {}
                                : { reference: average })}
                        />
                    </span>
                )}
            </TableCell>
        </TableRow>
    );
}
