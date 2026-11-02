import { Head, setLayoutProps, usePage } from '@inertiajs/react';
import { UserRound } from 'lucide-react';
import PageHeader from '@/components/humetria/page-header';
import AssessmentFacts from '@/components/seating/assessment-facts';
import AssessmentForm from '@/components/seating/assessment-form';
import AssessmentResult from '@/components/seating/assessment-result';
import AssessmentExtras from '@/components/seating/assessment-extras';
import RequestLinkButton from '@/components/seating/request-link-button';
import { Badge } from '@/components/ui/badge';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { index } from '@/routes/tenant/seating';
import { index as assessments } from '@/routes/tenant/seating/assessments';
import { resend } from '@/routes/tenant/seating/acknowledgments';
import type {
    AssessmentCondition,
    AssessmentFact,
    AssessmentRecord,
    MeasureSuggestions,
    Referral,
    SeatOption,
} from '@/types';

export default function SeatingAssessment({
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
    const signed = assessment.status === 'signed';
    setLayoutProps({
        breadcrumbs: [
            { title: 'Ley Silla', href: index(tenantSlug) },
            {
                title: 'Evaluaciones',
                href: assessments([tenantSlug, assessment.workCenterId]),
            },
            { title: person },
        ],
    });

    return (
        <>
            <Head title={`Evaluación de ${person}`} />
            <div className="flex w-full max-w-[880px] flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <PageHeader
                    title={person}
                    status={
                        <Badge variant={signed ? 'secondary' : 'outline'}>
                            {assessment.statusLabel}
                        </Badge>
                    }
                    facts={[{ icon: UserRound, text: fact('VIII') }]}
                >
                    {canResend && (
                        <RequestLinkButton
                            form={resend.form([tenantSlug, assessment.id])}
                            label="Reenviar enlace a la persona"
                        />
                    )}
                </PageHeader>
                {signed && (
                    <AssessmentResult
                        assessment={assessment}
                        suggestions={suggestions}
                    />
                )}
                <div className="card p-6 [&>section:first-child]:border-t-0 [&>section:first-child]:pt-0">
                    <AssessmentFacts facts={facts} />
                </div>
                <div className="card p-6 [&>form>section:first-child]:border-t-0 [&>form>section:first-child]:pt-0">
                    <AssessmentForm
                        assessment={assessment}
                        conditions={conditions}
                        editable={canEdit}
                    />
                </div>
                <AssessmentExtras
                    assessmentId={assessment.id}
                    referrals={referrals}
                    canRefer={canRefer}
                    canPropose={canPropose}
                    measureKinds={measureKinds}
                    suggestions={suggestions}
                    canSign={canSign}
                    signError={errors['sign']}
                    workCenterId={assessment.workCenterId}
                    minutesTitle={minutesTitle}
                    canRecordMinutes={canRecordMinutes}
                />
            </div>
        </>
    );
}
