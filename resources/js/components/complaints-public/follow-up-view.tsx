import { LogOut, Undo2 } from 'lucide-react';
import FollowUpReply from '@/components/complaints-public/follow-up-reply';
import FollowUpSteps from '@/components/complaints-public/follow-up-steps';
import FollowUpThread from '@/components/complaints-public/follow-up-thread';
import ConfirmDialog from '@/components/humetria/confirm-dialog';
import { Button } from '@/components/ui/button';
import { describeDate } from '@/lib/calendar-date';
import { followUpTitle } from '@/lib/complaints/follow-up';
import { countOf } from '@/lib/plural';
import type { FollowUp } from '@/types';

type Actions = {
    errors: Record<string, string | undefined>;
    processing: boolean;
    onPage: (page: number) => void;
    onReply: (body: string, onDone: () => void) => void;
    onWithdraw: () => void;
    /** Forgets the reference and the key and goes back to asking for them. */
    onLeave: () => void;
};

/** Where the complaint stands, in general words; what has been written; and what the person may still do. */
export default function FollowUpView({
    reference,
    view,
    actions,
}: {
    reference: string;
    view: FollowUp;
    actions: Actions;
}) {
    return (
        <>
            <div className="flex flex-col gap-1.5">
                <p className="font-mono text-sm font-semibold text-muted-foreground">
                    {reference}
                </p>
                <h1 className="text-[26px] leading-8 font-semibold tracking-[-0.01em]">
                    {followUpTitle(view.status)}
                </h1>
                <p className="text-muted-foreground">
                    {view.resolution ??
                        `Le responderemos a más tardar el ${describeDate(view.respondBy) ?? view.respondBy}.`}
                </p>
            </div>
            <FollowUpSteps view={view} />
            <p className="text-sm text-muted-foreground">
                {view.handlers === 0
                    ? 'Por ahora no la tiene asignada nadie: la empresa lo sabe.'
                    : `La atiende${view.handlers === 1 ? '' : 'n'} ${countOf(view.handlers, 'persona', 'personas')}. No le diremos quiénes son.`}
            </p>
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
                        <Button variant="ghost" className="self-start">
                            <Undo2 aria-hidden />
                            Retirar mi queja
                        </Button>
                    }
                />
            )}
            <Button
                variant="outline"
                className="w-full"
                onClick={actions.onLeave}
            >
                <LogOut aria-hidden />
                Salir
            </Button>
        </>
    );
}
