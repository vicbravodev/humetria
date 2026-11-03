import AnonymityLimits from '@/components/complaints-public/anonymity-limits';
import HowItWorks from '@/components/complaints-public/how-it-works';
import ReceiptCard from '@/components/complaints-public/receipt-card';
import ReviewStep from '@/components/complaints-public/step-review';
import StepNav from '@/components/complaints-public/step-nav';
import StoryStep from '@/components/complaints-public/step-story';
import StepType from '@/components/complaints-public/step-type';
import WhomStep from '@/components/complaints-public/step-whom';
import { Button } from '@/components/ui/button';
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
        return <ReceiptCard receipt={state.receipt} />;
    }

    if (state.step === 'intro') {
        return (
            <>
                <HowItWorks {...words} />
                <AnonymityLimits />
                <Button className="self-start" onClick={state.next}>
                    Quiero presentar una queja
                </Button>
            </>
        );
    }

    return (
        <>
            {state.step === 'type' && (
                <StepType
                    types={form.types}
                    value={state.draft.type}
                    onChange={(type) => state.change({ type, category: '' })}
                />
            )}
            {state.step === 'story' && (
                <StoryStep
                    draft={state.draft}
                    categories={form.categories.filter(
                        (category) => category.type === state.draft.type,
                    )}
                    limits={form.descriptionLimits}
                    today={form.today}
                    onChange={state.change}
                />
            )}
            {state.step === 'whom' && (
                <WhomStep
                    code={form.code}
                    kinds={form.kinds}
                    draft={state.draft}
                    onChange={state.change}
                />
            )}
            {state.step === 'review' && (
                <ReviewStep
                    draft={state.draft}
                    types={form.types}
                    kinds={form.kinds}
                    errors={state.errors}
                />
            )}
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
