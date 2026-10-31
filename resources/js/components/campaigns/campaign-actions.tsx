import { Link } from '@inertiajs/react';
import { LifeBuoy, Plus } from 'lucide-react';
import NewEvaluationDialog from '@/components/campaigns/new-evaluation-dialog';
import { Button } from '@/components/ui/button';
import { create as createTrauma } from '@/routes/tenant/campaigns/trauma';

/**
 * The NOM-035 page's two actions. On a phone each takes the whole row; beside the sidebar, below 1024 px, they keep one
 * row because the event's name shortens to its last word and stays whole for screen readers.
 */
export default function CampaignActions({
    tenantSlug,
    minGroup,
}: {
    tenantSlug: string;
    minGroup: number;
}) {
    return (
        <>
            <Button variant="outline" className="max-sm:flex-1" asChild>
                <Link href={createTrauma(tenantSlug)}>
                    <LifeBuoy aria-hidden />
                    <span>
                        <span className="md:max-lg:sr-only">Guía I por </span>
                        <span className="md:max-lg:capitalize">
                            acontecimiento
                        </span>
                    </span>
                </Link>
            </Button>
            <NewEvaluationDialog tenantSlug={tenantSlug} minGroup={minGroup}>
                <Button className="max-sm:flex-1">
                    <Plus aria-hidden />
                    Nueva evaluación
                </Button>
            </NewEvaluationDialog>
        </>
    );
}
