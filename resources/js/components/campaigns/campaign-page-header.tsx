import { CalendarDays, ClipboardCheck, Users } from 'lucide-react';
import type { ReactNode } from 'react';
import CampaignStatusBadge from '@/components/campaigns/campaign-status-badge';
import PageHeader from '@/components/page-header';
import { headerFacts } from '@/lib/evaluation';
import type { CampaignDetail } from '@/types';

const ICONS = { people: Users, guides: ClipboardCheck, period: CalendarDays };

/**
 * The work center and the evaluation's state; a draft says what is left to do, a launched one its people, guides and
 * dates (pantallas Eval-Borrador, Eval-Activa, Eval-Cerrada).
 */
export default function CampaignPageHeader({
    campaign,
    canManage,
    children,
}: {
    campaign: CampaignDetail;
    canManage: boolean;
    children: ReactNode;
}) {
    const status = (
        <CampaignStatusBadge
            status={campaign.status}
            label={campaign.statusLabel}
        />
    );

    if (campaign.status === 'draft') {
        return (
            <PageHeader
                title={campaign.workCenter}
                status={status}
                description={
                    canManage
                        ? 'Revisa tres cosas y lanza. Nadie recibe nada hasta que lo hagas.'
                        : 'Todavía no se lanza: nadie ha recibido nada.'
                }
            >
                {children}
            </PageHeader>
        );
    }

    return (
        <PageHeader
            // A Guía I after an event says so; the periodic evaluation is the page's default and needs no caption.
            {...(campaign.kind === 'nom035_evaluation'
                ? {}
                : { caption: campaign.kindLabel })}
            title={campaign.workCenter}
            status={status}
            facts={headerFacts(campaign).map((fact) => ({
                icon: ICONS[fact.kind],
                text: fact.text,
            }))}
        >
            {children}
        </PageHeader>
    );
}
