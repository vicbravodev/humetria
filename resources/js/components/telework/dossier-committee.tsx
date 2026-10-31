import DossierMissing from '@/components/telework/dossier-missing';
import { describeDate } from '@/lib/calendar-date';
import type { DossierCommittee } from '@/types';

/** 5.12: when the Comisión was constituted (and its act) and who sits on it. */
export default function DossierCommitteeBlock({
    committee,
}: {
    committee: DossierCommittee | null;
}) {
    if (committee === null) {
        return <DossierMissing>la Comisión no está registrada.</DossierMissing>;
    }

    return (
        <div className="flex flex-col gap-1 text-[15px] leading-[22px]">
            <p>
                Acta de constitución del{' '}
                {describeDate(committee.constitutedOn) ?? '—'}
                {committee.actHref !== null && (
                    <>
                        {' '}
                        (
                        <a
                            href={committee.actHref}
                            className="text-primary underline"
                        >
                            PDF
                        </a>
                        )
                    </>
                )}
                .
            </p>
            <p>
                Integrantes:{' '}
                {committee.members.length === 0
                    ? 'sin integrantes con acceso'
                    : committee.members.join('; ')}
                .
            </p>
        </div>
    );
}
