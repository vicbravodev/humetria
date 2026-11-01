import { Form } from '@inertiajs/react';
import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import ReviewDecisionFields from '@/components/telework/review-decision-fields';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

/** Sends one decision to `action`; `extra` holds the fields that only some decisions need (the visit's date). */
export default function ReviewDecisionForm({
    action,
    idPrefix,
    submitLabel,
    extra,
    onSuccess = () => undefined,
}: {
    action: string;
    idPrefix: string;
    submitLabel: string;
    extra?: ReactNode;
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
                    <ReviewDecisionFields idPrefix={idPrefix} errors={errors} />
                    {/* What refuses the decision as a whole: the list was already decided, the place is retired, or the person has not answered a list to validate on. */}
                    <InputError
                        role="alert"
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
