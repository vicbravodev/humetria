import { Link } from '@inertiajs/react';
import {
    Table,
    TableBody,
    TableCaption,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { instrumentsText, progressCount } from '@/lib/campaigns';
import { show } from '@/routes/tenant/campaigns';
import type { CampaignSummary } from '@/types';

/** Closed campaigns go in a table, not in cards (design system: CampaignCard). */
export default function ClosedCampaignsTable({
    tenantSlug,
    campaigns,
}: {
    tenantSlug: string;
    campaigns: CampaignSummary[];
}) {
    return (
        <Table>
            <TableCaption className="sr-only">
                Evaluaciones cerradas
            </TableCaption>
            <TableHeader>
                <TableRow>
                    <TableHead>Centro de trabajo</TableHead>
                    <TableHead>Guías</TableHead>
                    <TableHead>Cerró</TableHead>
                    <TableHead className="text-right">Respuestas</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {campaigns.map((campaign) => (
                    <TableRow key={campaign.id}>
                        <TableCell className="whitespace-normal">
                            <Link
                                href={show([tenantSlug, campaign.id])}
                                className="font-medium underline-offset-4 hover:underline"
                            >
                                {campaign.workCenter}
                            </Link>
                            {campaign.kind === 'nom035_trauma' && (
                                <p className="text-sm text-muted-foreground">
                                    {campaign.kindLabel}
                                </p>
                            )}
                        </TableCell>
                        <TableCell className="text-sm">
                            {instrumentsText(campaign.instruments)}
                        </TableCell>
                        <TableCell className="text-sm">
                            {campaign.closedOn}
                        </TableCell>
                        <TableCell className="text-right text-sm tabular-nums">
                            {campaign.progress === null
                                ? '—'
                                : progressCount(campaign.progress)}
                        </TableCell>
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}
