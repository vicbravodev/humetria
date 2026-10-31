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
import { progressCount } from '@/lib/campaigns';
import { numeralsText, periodText } from '@/lib/evaluation-centers';
import { results, show } from '@/routes/tenant/campaigns';
import type { CampaignSummary } from '@/types';

/** The history of closed campaigns (pantalla NOM035): where, which guides, when it ran, who answered, its results. */
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
                Historial de evaluaciones
            </TableCaption>
            <TableHeader>
                <TableRow>
                    <TableHead>Centro de trabajo</TableHead>
                    <TableHead>Guías</TableHead>
                    <TableHead>Periodo</TableHead>
                    <TableHead className="text-right">Respuestas</TableHead>
                    <TableHead>
                        <span className="sr-only">Resultados</span>
                    </TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {campaigns.map((campaign) => (
                    <TableRow key={campaign.id}>
                        <TableCell className="whitespace-normal">
                            <p className="font-medium">{campaign.workCenter}</p>
                            {campaign.kind === 'nom035_trauma' && (
                                <p className="text-sm text-muted-foreground">
                                    {campaign.kindLabel}
                                </p>
                            )}
                        </TableCell>
                        <TableCell>
                            {numeralsText(campaign.instruments)}
                        </TableCell>
                        <TableCell>
                            {periodText(campaign.launchedOn, campaign.closedOn)}
                        </TableCell>
                        <TableCell className="text-right tabular-nums">
                            {campaign.progress === null
                                ? '—'
                                : progressCount(campaign.progress)}
                        </TableCell>
                        <TableCell className="text-right">
                            <Link
                                href={
                                    // Guía I by event has no group results: its page leads to the channeling.
                                    campaign.kind === 'nom035_trauma'
                                        ? show([tenantSlug, campaign.id])
                                        : results([tenantSlug, campaign.id])
                                }
                                className="touch-hit text-sm font-medium text-primary underline-offset-4 hover:underline"
                            >
                                Resultados
                                <span className="sr-only">
                                    {' '}
                                    de {campaign.workCenter},{' '}
                                    {campaign.closedOn}
                                </span>
                            </Link>
                        </TableCell>
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}
