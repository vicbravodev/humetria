import { Link } from '@inertiajs/react';
import { Calendar, ClipboardCheck, FileText, UserRound } from 'lucide-react';
import { useState } from 'react';
import PageHeader from '@/components/humetria/page-header';
import ResponsibleDialog from '@/components/interventions/responsible-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { stateVariant } from '@/lib/interventions';
import { report, results } from '@/routes/tenant/campaigns';
import type {
    PageFact,
    ProgramCampaign,
    ProgramData,
    ProgramMember,
} from '@/types';

/**
 * The top of the Programa (pantalla Eval-Cerrada): the work center, where the Programa stands, the evaluation it comes
 * from, who answers for it, and the way to the results and the 7.7 report. RH changes who answers for it from here.
 */
export default function ProgramHeader({
    campaign,
    program,
    members,
}: {
    campaign: ProgramCampaign;
    program: ProgramData;
    /** Who can answer for it; null for whoever only reads the Programa. */
    members: ProgramMember[] | null;
}) {
    const tenantSlug = useTenantSlug();
    const [editing, setEditing] = useState(false);
    const facts: PageFact[] = [
        {
            icon: Calendar,
            text: `Evaluación cerrada el ${campaign.closedOn ?? '—'}`,
        },
        { icon: ClipboardCheck, text: campaign.guide ?? 'Sin guía' },
        {
            icon: UserRound,
            text:
                program.responsible === null
                    ? 'Sin responsable'
                    : `Responsable: ${program.responsible.name}`,
        },
    ];

    return (
        <PageHeader
            caption={campaign.workCenter}
            title="Programa de intervención"
            status={
                <Badge variant={stateVariant(program.state.value)}>
                    {program.state.label}
                </Badge>
            }
            facts={facts}
        >
            {members !== null && (
                <>
                    <Button variant="outline" onClick={() => setEditing(true)}>
                        <UserRound aria-hidden />
                        Responsable
                    </Button>
                    {editing && (
                        <ResponsibleDialog
                            open={editing}
                            onOpenChange={setEditing}
                            campaignId={campaign.id}
                            program={program}
                            members={members}
                        />
                    )}
                </>
            )}
            <Button asChild variant="outline">
                <Link href={report([tenantSlug, campaign.id])}>
                    <FileText aria-hidden />
                    Informe 7.7
                </Link>
            </Button>
            <Button asChild>
                <Link href={results([tenantSlug, campaign.id])}>
                    Ver resultados
                </Link>
            </Button>
        </PageHeader>
    );
}
