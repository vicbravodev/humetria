import { Head } from '@inertiajs/react';
import FollowUpForm from '@/components/complaints-public/follow-up-form';
import FollowUpView from '@/components/complaints-public/follow-up-view';
import PublicShell from '@/components/complaints-public/public-shell';
import { useFollowUp } from '@/hooks/use-follow-up';

export default function FollowUpPage({
    code,
    company,
}: {
    code: string;
    company: string;
}) {
    const state = useFollowUp(code);

    return (
        <PublicShell company={company}>
            <Head title="Seguimiento de su queja" />
            <div className="flex w-full max-w-2xl flex-col gap-6">
                <div className="flex flex-col gap-2">
                    <h1 className="text-[26px] leading-[34px] font-semibold tracking-[-0.01em]">
                        Seguimiento de su queja
                    </h1>
                    <p className="text-muted-foreground">
                        Vea cómo va su caso, lea lo que le responden y escriba
                        si tiene algo más que decir.
                    </p>
                </div>
                {state.view === undefined ? (
                    <FollowUpForm
                        error={state.errors['reference']}
                        processing={state.processing}
                        onOpen={state.open}
                    />
                ) : (
                    <FollowUpView
                        view={state.view}
                        actions={{
                            errors: state.errors,
                            processing: state.processing,
                            onPage: state.page,
                            onReply: state.reply,
                            onWithdraw: state.withdraw,
                        }}
                    />
                )}
            </div>
        </PublicShell>
    );
}
