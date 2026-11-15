import { Form } from '@inertiajs/react';
import { useState } from 'react';
import SubscriptionFormBody from '@/components/billing/subscription-form-body';
import { useLivePreview } from '@/hooks/use-live-preview';
import { parseSubscriptionPreview } from '@/lib/billing-preview';
import { planDefaults } from '@/lib/billing-form';
import { preview, update } from '@/routes/billing/subscriptions';
import type { AccountCompanyRow, AccountKind } from '@/types';

/**
 * The plan of a company, with what it would cost beside it, asked again as the form is typed: nobody accepts a price they
 * have not seen. The form starts from the plan the company has, or from a new one for the people it already has.
 */
export default function SubscriptionForm({
    company,
    accountKind,
    today,
    onDone,
}: {
    company: AccountCompanyRow;
    accountKind: AccountKind;
    today: string;
    onDone: () => void;
}) {
    const defaults = planDefaults(company, today);
    const [choice, setChoice] = useState({
        plan: defaults.plan,
        profile: defaults.profile,
    });
    const [status, setStatus] = useState(defaults.status);
    const live = useLivePreview(
        preview.url(company.company_id),
        parseSubscriptionPreview,
    );

    return (
        <Form
            {...update.form(company.company_id)}
            noValidate
            options={{ preserveScroll: true }}
            onSuccess={onDone}
            onChange={live.schedule}
            onClick={live.schedule}
            onKeyUp={live.schedule}
        >
            {({ processing, errors }) => (
                <div ref={live.anchorRef} className="grid gap-6">
                    <SubscriptionFormBody
                        defaults={defaults}
                        accountKind={accountKind}
                        choice={choice}
                        status={status}
                        preview={live.result}
                        errors={{ ...live.errors, ...errors }}
                        processing={processing}
                        onChoice={(next) => {
                            setChoice(next);
                            live.schedule();
                        }}
                        onStatus={setStatus}
                    />
                </div>
            )}
        </Form>
    );
}
