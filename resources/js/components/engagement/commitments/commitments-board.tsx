import { Plus, Target } from 'lucide-react';
import { useState } from 'react';
import CommitmentCard from '@/components/engagement/commitments/commitment-card';
import CommitmentDialog from '@/components/engagement/commitments/commitment-dialog';
import SuggestedActions from '@/components/engagement/commitments/suggested-actions';
import CountTabs from '@/components/humetria/count-tabs';
import EmptyState from '@/components/humetria/empty-state';
import Pagination from '@/components/humetria/pagination';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { index } from '@/routes/tenant/climate/commitments';
import type { CommitmentRow, EngagementCommitments } from '@/types';

/** The commitments of a closed survey by state, a page at a time, with the way to write and edit them. */
export default function CommitmentsBoard(props: EngagementCommitments) {
    const { survey, commitments, counts, statuses, canManage } = props;
    const tenantSlug = useTenantSlug();
    const [dialog, setDialog] = useState<{
        editing: CommitmentRow | null;
    } | null>(null);
    const tab = (value: string | null, label: string, count: number) => ({
        label,
        count,
        href: index([tenantSlug, survey.id], {
            query: value === null ? {} : { status: value },
        }),
        active: props.status === value,
    });

    return (
        <div className="flex flex-col gap-6">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <CountTabs
                    label="Estado de los compromisos"
                    tabs={[
                        tab(null, 'Todos', counts.all),
                        ...statuses.map((status) =>
                            tab(
                                status.value,
                                status.label,
                                counts[status.value],
                            ),
                        ),
                    ]}
                />
                {canManage && (
                    <Button onClick={() => setDialog({ editing: null })}>
                        <Plus aria-hidden />
                        Nuevo compromiso
                    </Button>
                )}
            </div>
            {commitments.total === 0 ? (
                <EmptyState icon={Target} title="Sin compromisos en esta vista">
                    {canManage
                        ? 'Escribe lo que la empresa hará con lo que dijeron en la encuesta: quién responde y para cuándo. Máximo 3 por grupo.'
                        : 'Cuando RH registre los compromisos de la empresa, aparecerán aquí.'}
                </EmptyState>
            ) : (
                <ul className="flex flex-col gap-4">
                    {commitments.data.map((commitment) => (
                        <CommitmentCard
                            key={commitment.id}
                            surveyId={survey.id}
                            commitment={commitment}
                            canManage={canManage}
                            onEdit={(editing) => setDialog({ editing })}
                        />
                    ))}
                </ul>
            )}
            <Pagination
                page={commitments}
                label="Páginas de compromisos"
                pinned={false}
            />
            {canManage && <SuggestedActions suggestions={props.suggestions} />}
            {canManage && dialog !== null && (
                <CommitmentDialog
                    open
                    onOpenChange={() => setDialog(null)}
                    surveyId={survey.id}
                    editing={dialog.editing}
                    groups={props.groups}
                    drivers={props.drivers}
                />
            )}
        </div>
    );
}
