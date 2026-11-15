import { Link } from '@inertiajs/react';
import RemindDialog from '@/components/engagement/remind-dialog';
import { Button } from '@/components/ui/button';
import { useEngagementRoutes } from '@/hooks/use-engagement-routes';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import type { ClimateSurveyCard } from '@/types';

/** «Ver avance» (or «Continuar borrador») and, for who manages a survey in progress with people missing, the reminder. */
export default function SurveyCardActions({
    survey,
    canManage,
}: {
    survey: ClimateSurveyCard;
    canManage: boolean;
}) {
    const tenantSlug = useTenantSlug();
    const routes = useEngagementRoutes();
    const pending = (survey.invited ?? 0) - (survey.answered ?? 0);

    return (
        <div className="flex flex-wrap gap-2">
            <Button asChild>
                <Link href={routes.show([tenantSlug, survey.id])}>
                    {survey.status === 'draft'
                        ? 'Continuar borrador'
                        : 'Ver avance'}
                </Link>
            </Button>
            {canManage && survey.status === 'active' && pending > 0 && (
                <RemindDialog surveyId={survey.id} pending={pending} outline />
            )}
        </div>
    );
}
