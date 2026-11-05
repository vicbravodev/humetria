import AssessmentMinutes from '@/components/seating/assessment-minutes';
import ProposeMeasureForm from '@/components/seating/propose-measure-form';
import ReferralsSection from '@/components/seating/referrals-section';
import type { MeasureSuggestions, Referral, SeatOption } from '@/types';

/** What goes under the assessment: the referrals, its minutes and the measures the Comisión proposes. */
export default function AssessmentExtras({
    assessmentId,
    referrals,
    canRefer,
    canPropose,
    measureKinds,
    suggestions,
    workCenterId,
    minutesTitle,
    canRecordMinutes,
}: {
    assessmentId: string;
    referrals: Referral[];
    canRefer: boolean;
    canPropose: boolean;
    measureKinds: SeatOption[];
    suggestions: MeasureSuggestions | null;
    workCenterId: string;
    minutesTitle: string | null;
    canRecordMinutes: boolean;
}) {
    const first =
        'card p-6 [&>section:first-child]:border-t-0 [&>section:first-child]:pt-0';

    return (
        <>
            <div className={first}>
                <ReferralsSection
                    assessmentId={assessmentId}
                    referrals={referrals}
                    canRefer={canRefer}
                />
            </div>
            {(minutesTitle !== null || canRecordMinutes) && (
                <div className={first}>
                    <AssessmentMinutes
                        assessmentId={assessmentId}
                        workCenterId={workCenterId}
                        current={minutesTitle}
                        canRecord={canRecordMinutes}
                    />
                </div>
            )}
            {canPropose && (
                <div className={first}>
                    <ProposeMeasureForm
                        assessmentId={assessmentId}
                        kinds={measureKinds}
                        suggestions={suggestions}
                    />
                </div>
            )}
        </>
    );
}
