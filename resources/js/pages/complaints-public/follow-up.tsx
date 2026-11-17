import { Head } from '@inertiajs/react';
import FollowUpForm from '@/components/complaints-public/follow-up-form';
import FollowUpPaused from '@/components/complaints-public/follow-up-paused';
import FollowUpView from '@/components/complaints-public/follow-up-view';
import PublicShell from '@/components/complaints-public/public-shell';
import { useFollowUp } from '@/hooks/use-follow-up';
import type { ExternalAlternative } from '@/types';

/** «La persona ve cómo va su queja» (board V4-Quejas-Seguimiento): with its reference and key, its state, messages and a reply. */
export default function FollowUpPage({
    code,
    company,
    paused,
    alternative,
}: {
    code: string;
    company: string;
    paused: boolean;
    alternative: ExternalAlternative;
}) {
    const state = useFollowUp(code);

    const notice = paused && <FollowUpPaused alternative={alternative} />;

    return (
        <>
            <Head title="Ver cómo va su queja" />
            {state.view === undefined ? (
                <FollowUpForm
                    company={company}
                    notice={notice}
                    error={state.errors['reference']}
                    processing={state.processing}
                    onOpen={state.open}
                />
            ) : (
                <PublicShell company={company}>
                    <div className="flex w-full max-w-xl flex-col gap-6">
                        {notice}
                        <FollowUpView
                            reference={state.reference}
                            view={state.view}
                            actions={{
                                errors: state.errors,
                                processing: state.processing,
                                onPage: state.page,
                                onReply: state.reply,
                                onWithdraw: state.withdraw,
                                onLeave: state.leave,
                            }}
                        />
                    </div>
                </PublicShell>
            )}
        </>
    );
}
