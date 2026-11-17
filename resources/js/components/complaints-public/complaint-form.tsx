import ComplaintStep from '@/components/complaints-public/complaint-step';
import FormIntro from '@/components/complaints-public/form-intro';
import IntroActions from '@/components/complaints-public/intro-actions';
import PublicShell from '@/components/complaints-public/public-shell';
import ReceiptCard from '@/components/complaints-public/receipt-card';
import StepNav from '@/components/complaints-public/step-nav';
import FlowProgress from '@/components/humetria/flow-progress';
import { useComplaintForm } from '@/hooks/use-complaint-form';
import { describeDate } from '@/lib/calendar-date';
import { STEP_COUNT, stepNumber } from '@/lib/complaints/form';
import { addDays } from '@/lib/evaluation';
import type { ExternalAlternative, PublicFormProps } from '@/types';

type Words = {
    company: string;
    procedure: string;
    acknowledgeWithinDays: number;
    resolveWithinDays: number;
    alternative: ExternalAlternative;
};

/**
 * The public form (boards QuejaInicio to QuejaRecibida), one step at a time: it reads, asks four things and, once the person
 * decides, sends. The way back and the progress stick to the top and the way forward to the bottom.
 */
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
                company={words.company}
                receipt={state.receipt}
                code={form.code}
                acknowledgeBy={describeDate(due) ?? due}
                named={state.draft.identityMode === 'confidential'}
            />
        );
    }

    if (state.step === 'intro') {
        return (
            <PublicShell
                company={words.company}
                actions={<IntroActions code={form.code} onStart={state.next} />}
            >
                <div className="flex w-full max-w-xl flex-1 flex-col">
                    <FormIntro {...words} />
                </div>
            </PublicShell>
        );
    }

    return (
        <PublicShell
            company={words.company}
            onBack={state.back}
            progress={
                <FlowProgress
                    label={`Paso ${stepNumber(state.step)} de ${STEP_COUNT}`}
                    left={null}
                    value={stepNumber(state.step)}
                    max={STEP_COUNT}
                />
            }
            actions={
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
            }
        >
            <div className="flex w-full max-w-xl flex-1 flex-col">
                <ComplaintStep form={form} state={state} />
            </div>
        </PublicShell>
    );
}
