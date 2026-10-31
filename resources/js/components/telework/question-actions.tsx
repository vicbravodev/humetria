import { router } from '@inertiajs/react';
import { ArrowDown, ArrowUp, Pencil, Trash2 } from 'lucide-react';
import ConfirmAction from '@/components/telework/confirm-action';
import { Button } from '@/components/ui/button';
import { destroy, move } from '@/routes/tenant/telework/checklist/questions';
import type { ChecklistQuestion } from '@/types';

/** Move, edit and remove a question of the draft; removing asks first. */
export default function QuestionActions({
    tenantSlug,
    workCenterId,
    question,
    onEdit,
}: {
    tenantSlug: string;
    workCenterId: string;
    question: ChecklistQuestion;
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
        <div className="flex shrink-0 gap-1">
            <Button
                variant="ghost"
                size="icon"
                aria-label={`Subir ${question.code}`}
                onClick={() => act(move(target), { direction: 'up' })}
            >
                <ArrowUp aria-hidden />
            </Button>
            <Button
                variant="ghost"
                size="icon"
                aria-label={`Bajar ${question.code}`}
                onClick={() => act(move(target), { direction: 'down' })}
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
            <ConfirmAction
                title={`¿Quitar ${question.code} del borrador?`}
                description="La pregunta sale de este borrador. Las versiones ya publicadas no cambian."
                confirmLabel="Quitar pregunta"
                destructive
                onConfirm={() => act(destroy(target))}
            >
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label={`Quitar ${question.code}`}
                >
                    <Trash2 aria-hidden />
                </Button>
            </ConfirmAction>
        </div>
    );
}
