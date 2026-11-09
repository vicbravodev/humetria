import { Head } from '@inertiajs/react';
import AgreementCard from '@/components/attendance/agreement-card';
import EnrollDone from '@/components/attendance/enroll-done';
import EnrollForm from '@/components/attendance/enroll-form';
import PersonFrame from '@/components/attendance/person-frame';
import { useEnrollment } from '@/hooks/use-enrollment';
import { deviceLabel } from '@/lib/attendance/webauthn';

/** A person links their phone with the link RH sent them: reads and accepts the agreement, and creates a key (no account). */
export default function Enroll({
    company,
    name,
    agreement,
    optionsUrl,
    registerUrl,
}: {
    company: string;
    name: string;
    agreement: { version: number; body: string };
    optionsUrl: string;
    registerUrl: string;
}) {
    const { working, done, error, enroll } = useEnrollment({
        optionsUrl,
        registerUrl,
        agreement: agreement.version,
        label: deviceLabel(navigator.userAgent),
    });

    return (
        <PersonFrame company={company}>
            <Head title="Vincular su teléfono" />
            <div className="flex w-full max-w-2xl flex-col gap-6">
                <div className="flex flex-col gap-2">
                    <h1 className="text-[26px] leading-[34px] font-semibold tracking-[-0.01em]">
                        Vincule su teléfono, {name}
                    </h1>
                    <p className="text-muted-foreground">
                        {company} registra su jornada de forma electrónica. Lea
                        el acuerdo y, si está de acuerdo, acéptelo para marcar
                        su entrada y su salida con este teléfono.
                    </p>
                </div>
                {done ? (
                    <EnrollDone />
                ) : (
                    <>
                        <AgreementCard
                            version={agreement.version}
                            body={agreement.body}
                        />
                        <EnrollForm
                            working={working}
                            error={error}
                            onEnroll={() => void enroll()}
                        />
                    </>
                )}
            </div>
        </PersonFrame>
    );
}
