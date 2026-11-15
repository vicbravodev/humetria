import { Form, router } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import RowMenu from '@/components/humetria/row-menu';
import { Button } from '@/components/ui/button';
import { useEngagementRoutes } from '@/hooks/use-engagement-routes';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { menuSteps, primaryStep } from '@/lib/engagement/commitments';
import type { CommitmentRow } from '@/types';

const VERB: Record<CommitmentRow['status'], string> = {
    open: 'Reabrir',
    in_progress: 'Marcar en marcha',
    done: 'Marcar cumplido',
    dropped: 'Descartar',
};

/**
 * What a card lets do: one button with the next state of the cycle and, under «Más», editing and every other state.
 */
export default function CommitmentStatusActions({
    surveyId,
    commitment,
    onEdit,
}: {
    surveyId: string;
    commitment: CommitmentRow;
    onEdit: () => void;
}) {
    const tenantSlug = useTenantSlug();
    const routes = useEngagementRoutes();
    const at: [string, string, string] = [tenantSlug, surveyId, commitment.id];
    const next = primaryStep(commitment.status);
    const move = (status: CommitmentRow['status']) =>
        router.put(
            routes.commitments.status.url(at),
            { status },
            { preserveScroll: true },
        );

    return (
        <div className="flex items-center justify-between gap-2 border-t pt-3">
            {next === null ? (
                <span />
            ) : (
                <Form
                    {...routes.commitments.status.form.put(at)}
                    options={{ preserveScroll: true }}
                >
                    {({ processing }) => (
                        <>
                            <input type="hidden" name="status" value={next} />
                            <Button variant="outline" disabled={processing}>
                                {VERB[next]}
                            </Button>
                        </>
                    )}
                </Form>
            )}
            <RowMenu
                label={`Más acciones de «${commitment.will_do}»`}
                items={[
                    { label: 'Editar', icon: Pencil, onSelect: onEdit },
                    ...menuSteps(commitment.status).map((status) => ({
                        label: VERB[status],
                        onSelect: () => move(status),
                        destructive: status === 'dropped',
                    })),
                ]}
            />
        </div>
    );
}
