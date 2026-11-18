import { Head } from '@inertiajs/react';
import ComplaintForm from '@/components/complaints-public/complaint-form';
import type { ExternalAlternative, PublicFormProps } from '@/types';

/** «Presentar una queja» (boards Queja*), on the phone first: no account, no session, no cookie; always in «usted». */
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
        <>
            <Head title="Presentar una queja o denuncia" />
            <ComplaintForm
                form={form}
                words={{
                    company,
                    procedure,
                    acknowledgeWithinDays,
                    resolveWithinDays,
                    alternative,
                }}
            />
        </>
    );
}
