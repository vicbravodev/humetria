import { Head, setLayoutProps, usePage } from '@inertiajs/react';
import AssessmentFacts from '@/components/seating/assessment-facts';
import AssessmentHeader from '@/components/seating/assessment-header';
import AssessmentLead from '@/components/seating/assessment-lead';
import AssessmentExtras from '@/components/seating/assessment-extras';
import AssessmentWorkspace from '@/components/seating/assessment-workspace';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { seatingCrumbs } from '@/lib/seating/crumbs';
import { index as assessments } from '@/routes/tenant/seating/assessments';
import type {
    AssessmentCondition,
    AssessmentFact,
    AssessmentRecord,
    MeasureSuggestions,
    Referral,
    SeatOption,
} from '@/types';

export default function SeatingAssessment({
    workCenter,
    assessment,
    facts,
    conditions,
    suggestions,
    canSign,
    canEdit,
    referrals,
    canRefer,
    canResend,
    canPropose,
    measureKinds,
    minutesTitle,
    canRecordMinutes,
}: {
    workCenter: { id: string; name: string };
    assessment: AssessmentRecord;
    facts: AssessmentFact[];
    conditions: AssessmentCondition[];
    suggestions: MeasureSuggestions | null;
    canSign: boolean;
    canEdit: boolean;
    referrals: Referral[];
    canRefer: boolean;
    canResend: boolean;
    canPropose: boolean;
    measureKinds: SeatOption[];
    minutesTitle: string | null;
    canRecordMinutes: boolean;
}) {
    const tenantSlug = useTenantSlug();
    const { errors } = usePage().props;
    const fact = (numeral: string) =>
        facts.find((item) => item.numeral === numeral)?.value ?? '';
    const person = fact('VII');
    const list = assessments([tenantSlug, workCenter.id]);
    setLayoutProps({
        breadcrumbs: seatingCrumbs(
            tenantSlug,
            workCenter,
            { title: 'Evaluaciones', href: list },
            { title: person },
        ),
    });

    return (
        <>
            <Head title={`Evaluación de ${person}`} />
            <div className="mx-auto flex w-full max-w-[1280px] flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <AssessmentHeader
                    person={person}
                    position={fact('VIII')}
                    assessment={assessment}
                    canResend={canResend}
                />
                <AssessmentWorkspace
                    assessment={assessment}
                    conditions={conditions}
                    canEdit={canEdit}
                    canSign={canSign}
                    signError={errors['sign']}
                    before={
                        <AssessmentLead
                            assessment={assessment}
                            suggestions={suggestions}
                        />
                    }
                    after={
                        <>
                            <div className="card p-6 [&>section:first-child]:border-t-0 [&>section:first-child]:pt-0">
                                <AssessmentFacts facts={facts} />
                            </div>
                            <AssessmentExtras
                                assessmentId={assessment.id}
                                referrals={referrals}
                                canRefer={canRefer}
                                canPropose={canPropose}
                                measureKinds={measureKinds}
                                suggestions={suggestions}
                                workCenterId={assessment.workCenterId}
                                minutesTitle={minutesTitle}
                                canRecordMinutes={canRecordMinutes}
                            />
                        </>
                    }
                />
            </div>
        </>
    );
}
