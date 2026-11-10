import { CalendarClock, UserRound } from 'lucide-react';
import CommitmentStatusActions from '@/components/engagement/commitments/commitment-status-actions';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { describeDate } from '@/lib/calendar-date';
import { statusVariant } from '@/lib/engagement/commitments';
import type { CommitmentRow } from '@/types';

/** One commitment, as the portal will read it: what people said, what the company will do, who and by when. */
export default function CommitmentCard({
    surveyId,
    commitment,
    canManage,
    onEdit,
}: {
    surveyId: string;
    commitment: CommitmentRow;
    canManage: boolean;
    onEdit: (commitment: CommitmentRow) => void;
}) {
    return (
        <li className="flex flex-col gap-4 card p-5">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <p className="font-semibold">
                    {commitment.group_name}
                    {commitment.driver_label !== null && (
                        <span className="font-normal text-muted-foreground">
                            {' · '}
                            {commitment.driver_label}
                        </span>
                    )}
                </p>
                <Badge
                    variant={statusVariant(
                        commitment.status,
                        commitment.overdue,
                    )}
                >
                    {commitment.overdue ? 'Vencido' : commitment.status_label}
                </Badge>
            </div>
            <dl className="grid gap-4 md:grid-cols-2">
                <div>
                    <dt className="text-meta text-muted-foreground">
                        Nos dijeron
                    </dt>
                    <dd className="mt-1">{commitment.heard}</dd>
                </div>
                <div>
                    <dt className="text-meta text-muted-foreground">Haremos</dt>
                    <dd className="mt-1">{commitment.will_do}</dd>
                </div>
            </dl>
            <ul className="flex flex-wrap gap-x-5 gap-y-1 text-meta text-muted-foreground">
                <li className="flex items-center gap-1.5">
                    <UserRound aria-hidden className="size-4" />
                    {commitment.owner_name}
                </li>
                <li className="flex items-center gap-1.5">
                    <CalendarClock aria-hidden className="size-4" />
                    {describeDate(commitment.due_on)}
                </li>
            </ul>
            {canManage && (
                <div className="flex flex-wrap items-center gap-1 border-t pt-3">
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => onEdit(commitment)}
                    >
                        Editar
                    </Button>
                    <CommitmentStatusActions
                        surveyId={surveyId}
                        commitment={commitment}
                    />
                </div>
            )}
        </li>
    );
}
