import ComplaintStep from '@/components/complaints-public/complaint-step';
import FormIntro from '@/components/complaints-public/form-intro';
import ReceiptCard from '@/components/complaints-public/receipt-card';
import StepNav from '@/components/complaints-public/step-nav';
import StepProgress from '@/components/complaints-public/step-progress';
import { useComplaintForm } from '@/hooks/use-complaint-form';
import { describeDate } from '@/lib/calendar-date';
import { addDays } from '@/lib/evaluation';
import type { ExternalAlternative, PublicFormProps } from '@/types';

type Words = {
    procedure: string;
    acknowledgeWithinDays: number;
    resolveWithinDays: number;
    alternative: ExternalAlternative;
};

/** The public form (board V4-Quejas-Form), one step at a time: it reads, asks four things and, once the person decides, sends. */
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
        const due = addDays(form.today, words.acknowledgeWithinDays);

        return (
            <ReceiptCard
                receipt={state.receipt}
                code={form.code}
                acknowledgeBy={describeDate(due) ?? due}
            />
        );
    }

    if (state.step === 'intro') {
        return <FormIntro code={form.code} {...words} onStart={state.next} />;
    }

    return (
        <div className="flex flex-1 flex-col gap-5">
            <StepProgress step={state.step} onBack={state.back} />
            <ComplaintStep form={form} state={state} />
            <StepNav
                last={state.step === 'identity'}
                problem={state.problem}
                refused={Object.values(state.errors).filter(
                    (text): text is string => text !== undefined,
                )}
                processing={state.processing}
                onNext={state.next}
                onSend={state.send}
            />
        </div>
    );
}
