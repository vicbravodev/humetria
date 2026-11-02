import { router } from '@inertiajs/react';
import { ArrowDown, ArrowUp, Pencil, Trash2 } from 'lucide-react';
import ConfirmDialog from '@/components/humetria/confirm-dialog';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { destroy, move } from '@/routes/tenant/telework/checklist/questions';
import type { ChecklistQuestion } from '@/types';

/** Move, edit and remove a question of the draft; removing asks first. */
export default function QuestionActions({
    workCenterId,
    question,
    onEdit,
}: {
    workCenterId: string;
    question: ChecklistQuestion;
    onEdit: () => void;
}) {
    const tenantSlug = useTenantSlug();
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
            <ConfirmDialog
                trigger={
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label={`Quitar ${question.code}`}
                    >
                        <Trash2 aria-hidden />
                    </Button>
                }
                title={`¿Quitar ${question.code} del borrador?`}
                description="La pregunta sale de este borrador. Las versiones ya publicadas no cambian."
                confirmLabel="Quitar pregunta"
                onConfirm={() => act(destroy(target))}
            />
        </div>
    );
}
