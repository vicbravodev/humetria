import { Form } from '@inertiajs/react';
import DeleteDraftDialog from '@/components/campaigns/delete-draft-dialog';
import { Button } from '@/components/ui/button';
import { redraw } from '@/routes/tenant/campaigns';

/** What can be done to a draft before launching it: draw its sample again, or throw it away. */
export default function DraftActions({
    tenantSlug,
    campaignId,
    sampled,
}: {
    tenantSlug: string;
    campaignId: string;
    sampled: boolean;
}) {
    return (
        <div className="flex flex-wrap items-center gap-3">
            {sampled && (
                <Form {...redraw.form([tenantSlug, campaignId])}>
                    {({ processing }) => (
                        <Button
                            type="submit"
                            variant="outline"
                            disabled={processing}
                        >
                            Volver a sortear
                        </Button>
                    )}
                </Form>
            )}
            <DeleteDraftDialog
                tenantSlug={tenantSlug}
                campaignId={campaignId}
            />
        </div>
    );
}
