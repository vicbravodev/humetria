import { Undo2 } from 'lucide-react';
import FollowUpReply from '@/components/complaints-public/follow-up-reply';
import FollowUpThread from '@/components/complaints-public/follow-up-thread';
import ConfirmDialog from '@/components/humetria/confirm-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { countOf } from '@/lib/plural';
import type { FollowUp } from '@/types';

type Actions = {
    errors: Record<string, string | undefined>;
    processing: boolean;
    onPage: (page: number) => void;
    onReply: (body: string, onDone: () => void) => void;
    onWithdraw: () => void;
};

/** Where the complaint stands, in general words; what has been written; and what the person may still do. */
export default function FollowUpView({
    view,
    actions,
}: {
    view: FollowUp;
    actions: Actions;
}) {
    return (
        <>
            <section className="flex flex-col gap-3 card p-5">
                <div className="flex flex-wrap items-center gap-3">
                    <h2 className="text-xl font-semibold">Su queja</h2>
                    <Badge
                        variant={
                            view.status === 'closed' ? 'secondary' : 'default'
                        }
                    >
                        {view.statusLabel}
                    </Badge>
                </div>
                {view.resolution !== null && <p>{view.resolution}</p>}
                <p className="text-base text-muted-foreground">
                    {view.handlers === 0
                        ? 'Por ahora no la tiene asignada nadie: la empresa lo sabe.'
                        : `La atiende${view.handlers === 1 ? '' : 'n'} ${countOf(view.handlers, 'persona', 'personas')}. No le diremos quiénes son.`}
                </p>
            </section>
            <FollowUpThread
                entries={view.entries}
                processing={actions.processing}
                onPage={actions.onPage}
            />
            {view.canReply && (
                <FollowUpReply
                    error={actions.errors['body']}
                    processing={actions.processing}
                    onReply={actions.onReply}
                />
            )}
            {view.canWithdraw && (
                <ConfirmDialog
                    title="¿Retirar su queja?"
                    description="El caso se cierra y quienes lo atienden verán que usted lo retiró. Una persona encargada puede abrirlo de nuevo."
                    confirmLabel="Retirar mi queja"
                    onConfirm={actions.onWithdraw}
                    trigger={
                        <Button variant="outline" className="self-start">
                            <Undo2 aria-hidden />
                            Retirar mi queja
                        </Button>
                    }
                />
            )}
        </>
    );
}
