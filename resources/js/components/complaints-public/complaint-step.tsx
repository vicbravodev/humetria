import IdentityStep from '@/components/complaints-public/step-identity';
import StoryStep from '@/components/complaints-public/step-story';
import StepType from '@/components/complaints-public/step-type';
import WhomStep from '@/components/complaints-public/step-whom';
import type { useComplaintForm } from '@/hooks/use-complaint-form';
import { changesForType } from '@/lib/complaints/form';
import type { PublicFormProps } from '@/types';

/** The step the person is on, with what it needs from the form state. */
export default function ComplaintStep({
    form,
    state,
}: {
    form: PublicFormProps;
    state: ReturnType<typeof useComplaintForm>;
}) {
    const { draft, change } = state;

    switch (state.step) {
        case 'type':
            return (
                <StepType
                    types={form.types}
                    value={draft.type}
                    onChange={(type) => change(changesForType(type))}
                />
            );
        case 'story':
            return (
                <StoryStep
                    draft={draft}
                    categories={form.categories.filter(
                        (category) => category.type === draft.type,
                    )}
                    limits={form.descriptionLimits}
                    today={form.today}
                    onChange={change}
                />
            );
        case 'whom':
            return (
                <WhomStep
                    code={form.code}
                    kinds={form.kinds}
                    draft={draft}
                    onChange={change}
                />
            );
        case 'identity':
            return <IdentityStep draft={draft} onChange={change} />;
        default:
            return null;
    }
}
