import { router } from '@inertiajs/react';
import { ArrowDown, ArrowUp, Pencil, Trash2 } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    favorableLabel,
    originLabel,
    originVariant,
} from '@/lib/telework/checklist';
import { destroy, move } from '@/routes/tenant/telework/checklist/questions';
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
    const target = {
        tenant: tenantSlug,
        workCenter: workCenterId,
        question: question.id,
    };
    const act = (
        route: ReturnType<typeof move> | ReturnType<typeof destroy>,
        data: { direction?: string } = {},
    ) =>
        router.visit(route.url, {
            method: route.method,
            data,
            preserveScroll: true,
        });

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
                    <div className="flex shrink-0 gap-1">
                        <Button
                            variant="ghost"
                            size="icon"
                            aria-label={`Subir ${question.code}`}
                            onClick={() =>
                                act(move(target), { direction: 'up' })
                            }
                        >
                            <ArrowUp aria-hidden />
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon"
                            aria-label={`Bajar ${question.code}`}
                            onClick={() =>
                                act(move(target), { direction: 'down' })
                            }
                        >
                            <ArrowDown aria-hidden />
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon"
                            aria-label={`Editar ${question.code}`}
                            onClick={onEdit}
                        >
                            <Pencil aria-hidden />
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon"
                            aria-label={`Quitar ${question.code}`}
                            onClick={() => act(destroy(target))}
                        >
                            <Trash2 aria-hidden />
                        </Button>
                    </div>
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
