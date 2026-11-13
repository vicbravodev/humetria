import { Head } from '@inertiajs/react';
import { useState } from 'react';
import EnrollScreen from '@/components/attendance/enroll-screen';
import { useEnrollment } from '@/hooks/use-enrollment';
import type { EnrollStep } from '@/lib/attendance/enroll';
import { deviceLabel } from '@/lib/attendance/webauthn';

/**
 * A person links their phone with the link RH sent them, in four steps (board P1): reads and accepts the agreement, confirms
 * the link is theirs, creates a key (no account), and learns what follows. Anything that goes wrong says what to do (P3).
 */
export default function Enroll({
    company,
    name,
    jobTitle,
    linkHours,
    centerReady,
    punchUrl,
    agreement,
    optionsUrl,
    registerUrl,
}: {
    company: string;
    name: string;
    jobTitle: string;
    linkHours: number;
    /** Whether their center can already mark (a zone and, if the policy asks, a screen). */
    centerReady: boolean;
    punchUrl: string;
    agreement: { version: number; body: string };
    optionsUrl: string;
    registerUrl: string;
}) {
    const [step, setStep] = useState<EnrollStep>('agreement');
    const device = deviceLabel(navigator.userAgent);
    const enrollment = useEnrollment({
        optionsUrl,
        registerUrl,
        agreement: agreement.version,
        label: device,
    });

    return (
        <>
            <Head title="Vincular su teléfono" />
            <EnrollScreen
                step={step}
                setStep={setStep}
                company={company}
                name={name}
                jobTitle={jobTitle}
                linkHours={linkHours}
                centerReady={centerReady}
                punchUrl={punchUrl}
                agreement={agreement}
                enrollment={enrollment}
                device={device}
            />
        </>
    );
}
