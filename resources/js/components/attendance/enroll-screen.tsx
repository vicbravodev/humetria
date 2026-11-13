import EnrollAgreement from '@/components/attendance/enroll-agreement';
import EnrollConfirm from '@/components/attendance/enroll-confirm';
import EnrollDone from '@/components/attendance/enroll-done';
import EnrollExit from '@/components/attendance/enroll-exit';
import EnrollKey from '@/components/attendance/enroll-key';
import EnrollProblem from '@/components/attendance/enroll-problem';
import type { EnrollOutcome, EnrollStep } from '@/lib/attendance/enroll';
import { enrollProblem } from '@/lib/attendance/enroll';

/** The screen of the way to link the phone that fits where the person is (board P1), or what went wrong and what to do (P3). */
export default function EnrollScreen({
    step,
    setStep,
    company,
    name,
    jobTitle,
    linkHours,
    centerReady,
    punchUrl,
    agreement,
    enrollment,
    device,
}: {
    step: EnrollStep;
    setStep: (step: EnrollStep) => void;
    company: string;
    name: string;
    jobTitle: string;
    linkHours: number;
    centerReady: boolean;
    punchUrl: string;
    agreement: { version: number; body: string };
    enrollment: {
        working: boolean;
        done: boolean;
        failure: Exclude<EnrollOutcome, 'done'> | null;
        enroll: () => Promise<void>;
    };
    device: string;
}) {
    const { working, done, failure, enroll } = enrollment;

    if (done) {
        return (
            <EnrollDone
                company={company}
                device={device}
                punchUrl={punchUrl}
                ready={centerReady}
            />
        );
    }

    if (failure !== null) {
        return (
            <EnrollProblem
                company={company}
                problem={enrollProblem(failure, linkHours)}
                onRetry={() => void enroll()}
            />
        );
    }

    switch (step) {
        case 'agreement':
            return (
                <EnrollAgreement
                    company={company}
                    agreement={agreement}
                    onAccept={() => setStep('confirm')}
                    onLater={() => setStep('later')}
                />
            );
        case 'confirm':
            return (
                <EnrollConfirm
                    company={company}
                    name={name}
                    jobTitle={jobTitle}
                    onConfirm={() => setStep('key')}
                    onNotYou={() => setStep('not-you')}
                />
            );
        case 'key':
        case 'done':
            return (
                <EnrollKey
                    company={company}
                    working={working}
                    onCreate={() => void enroll()}
                />
            );
        default:
            return (
                <EnrollExit
                    company={company}
                    reason={step}
                    linkHours={linkHours}
                />
            );
    }
}
