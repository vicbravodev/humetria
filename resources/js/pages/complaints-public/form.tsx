import { Head } from '@inertiajs/react';
import ComplaintForm from '@/components/complaints-public/complaint-form';
import PublicShell from '@/components/complaints-public/public-shell';
import type { ExternalAlternative, PublicFormProps } from '@/types';

/** «Presentar una queja» (board V4-Quejas-Form), on the phone first: no account, no session, no cookie; always in «usted». */
export default function Form({
    company,
    procedure,
    acknowledgeWithinDays,
    resolveWithinDays,
    alternative,
    ...form
}: PublicFormProps & {
    company: string;
    procedure: string;
    acknowledgeWithinDays: number;
    resolveWithinDays: number;
    alternative: ExternalAlternative;
}) {
    return (
        <PublicShell company={company}>
            <Head title="Quejas y denuncias" />
            <div className="flex w-full max-w-xl flex-1 flex-col">
                <ComplaintForm
                    form={form}
                    words={{
                        procedure,
                        acknowledgeWithinDays,
                        resolveWithinDays,
                        alternative,
                    }}
                />
            </div>
        </PublicShell>
    );
}
