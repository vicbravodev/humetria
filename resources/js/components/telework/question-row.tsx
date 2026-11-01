import QuestionActions from '@/components/telework/question-actions';
import { Badge } from '@/components/ui/badge';
import {
    favorableLabel,
    originLabel,
    originVariant,
} from '@/lib/telework/checklist';
import type { ChecklistQuestion } from '@/types';

/** One question of the checklist: what it asks, where it comes from and, in a draft, how to change it. */
export default function QuestionRow({
    tenantSlug,
    workCenterId,
    question,
    requirementLabel,
    canEdit,
    onEdit,
}: {
    tenantSlug: string;
    workCenterId: string;
    question: ChecklistQuestion;
    requirementLabel: string | undefined;
    canEdit: boolean;
    onEdit: () => void;
}) {
    return (
        <li className="flex flex-col gap-2 px-5 py-4">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <p className="min-w-0 flex-1 text-[15px] leading-[22px]">
                    <span className="mr-2 font-mono text-[13px] text-muted-foreground">
                        {question.code}
                    </span>
                    {question.text}
                </p>
                {canEdit && (
                    <QuestionActions
                        tenantSlug={tenantSlug}
                        workCenterId={workCenterId}
                        question={question}
                        onEdit={onEdit}
                    />
                )}
            </div>
            <div className="flex flex-wrap items-center gap-2">
                <Badge variant={originVariant(question.origin)}>
                    {originLabel(question.origin)}
                </Badge>
                <Badge variant="outline">
                    {favorableLabel(question.favorable)}
                </Badge>
                {question.sensitive && (
                    <Badge variant="warning">Sensible</Badge>
                )}
                {requirementLabel !== undefined && (
                    <span className="text-[13px] leading-[18px] text-muted-foreground">
                        Cubre {question.requirement}: {requirementLabel}
                    </span>
                )}
            </div>
        </li>
    );
}
