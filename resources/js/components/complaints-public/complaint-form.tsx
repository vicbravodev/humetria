import AnonymityLimits from '@/components/complaints-public/anonymity-limits';
import ComplaintStep from '@/components/complaints-public/complaint-step';
import HowItWorks from '@/components/complaints-public/how-it-works';
import ReceiptCard from '@/components/complaints-public/receipt-card';
import StepNav from '@/components/complaints-public/step-nav';
import { Button } from '@/components/ui/button';
import { show } from '@/routes/complaints/follow-up';
import { useComplaintForm } from '@/hooks/use-complaint-form';
import type { PublicFormProps } from '@/types';

type Words = {
    procedure: string;
    acknowledgeWithinDays: number;
    resolveWithinDays: number;
};

/** The public form, one step at a time: it reads, asks, shows what will be sent and, once the person decides, sends. */
export default function ComplaintForm({
    form,
    words,
}: {
    form: PublicFormProps;
    words: Words;
}) {
    const state = useComplaintForm(
        form.code,
        form.ticket,
        form.descriptionLimits,
    );

    if (state.receipt !== undefined) {
        return <ReceiptCard receipt={state.receipt} code={form.code} />;
    }

    if (state.step === 'intro') {
        return (
            <>
                <HowItWorks {...words} />
                <AnonymityLimits />
                <div className="flex flex-wrap items-center gap-x-6 gap-y-3">
                    <Button onClick={state.next}>
                        Quiero presentar una queja
                    </Button>
                    <a
                        href={show.url({ code: form.code })}
                        className="touch-hit rounded-sm font-medium text-primary underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                    >
                        Ya presenté una queja: ver cómo va
                    </a>
                </div>
            </>
        );
    }

    return (
        <>
            <ComplaintStep form={form} state={state} />
            <StepNav
                step={state.step}
                problem={state.problem}
                processing={state.processing}
                onBack={state.back}
                onNext={state.next}
                onSend={state.send}
            />
        </>
    );
}
