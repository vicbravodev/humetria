import { Form } from '@inertiajs/react';
import type { ReactNode } from 'react';
import InputError from '@/components/humetria/input-error';
import ReviewDecisionFields from '@/components/telework/review-decision-fields';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

/** Sends one decision to `action`; `extra` holds the fields that only some decisions need (the visit's date). */
export default function ReviewDecisionForm({
    action,
    idPrefix,
    submitLabel,
    extra,
    unfavorable = 0,
    onSuccess = () => undefined,
}: {
    action: string;
    idPrefix: string;
    submitLabel: string;
    extra?: ReactNode;
    unfavorable?: number;
    onSuccess?: () => void;
}) {
    return (
        <Form
            action={action}
            method="post"
            noValidate
            resetOnSuccess
            onSuccess={onSuccess}
            className="grid gap-5"
        >
            {({ processing, errors }) => (
                <>
                    {extra}
                    <ReviewDecisionFields
                        idPrefix={idPrefix}
                        errors={errors}
                        unfavorable={unfavorable}
                    />
                    {/* What refuses the decision as a whole: the list was already decided, the place is retired, or the person has not answered a list to validate on. */}
                    <InputError
                        message={errors['application'] ?? errors['workplace']}
                    />
                    <Button
                        type="submit"
                        disabled={processing}
                        className="w-fit"
                    >
                        {processing && <Spinner />}
                        {submitLabel}
                    </Button>
                </>
            )}
        </Form>
    );
}
