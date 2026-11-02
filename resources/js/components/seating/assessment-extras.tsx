import AssessmentSign from '@/components/seating/assessment-sign';
import ProposeMeasureForm from '@/components/seating/propose-measure-form';
import ReferralsSection from '@/components/seating/referrals-section';
import type { MeasureSuggestions, Referral, SeatOption } from '@/types';

/** What goes under the assessment: the referrals, the measures the Comisión proposes and the signature. */
export default function AssessmentExtras({
    assessmentId,
    referrals,
    canRefer,
    canPropose,
    measureKinds,
    suggestions,
    canSign,
    signError,
}: {
    assessmentId: string;
    referrals: Referral[];
    canRefer: boolean;
    canPropose: boolean;
    measureKinds: SeatOption[];
    suggestions: MeasureSuggestions | null;
    canSign: boolean;
    signError: string | undefined;
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
            {canPropose && (
                <div className={first}>
                    <ProposeMeasureForm
                        assessmentId={assessmentId}
                        kinds={measureKinds}
                        suggestions={suggestions}
                    />
                </div>
            )}
            {canSign && (
                <AssessmentSign assessmentId={assessmentId} error={signError} />
            )}
        </>
    );
}
