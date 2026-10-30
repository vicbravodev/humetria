import { usePage } from '@inertiajs/react';

/** The company in the URL as an evaluation page needs it: its slug and name, and what the person may do there. */
export function useCampaignAccess(campaignId: string): {
    route: [string, string];
    company: string;
    canManage: boolean;
    canSeeGuideI: boolean;
} {
    const { tenant } = usePage().props;

    return {
        route: [tenant?.slug ?? '', campaignId],
        company: tenant?.trade_name ?? '',
        canManage: tenant?.can.manage_campaigns ?? false,
        canSeeGuideI: tenant?.can.view_trauma_screenings ?? false,
    };
}
