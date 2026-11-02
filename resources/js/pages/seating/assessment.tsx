import { Head, setLayoutProps, usePage } from '@inertiajs/react';
import { UserRound } from 'lucide-react';
import PageHeader from '@/components/humetria/page-header';
import AssessmentFacts from '@/components/seating/assessment-facts';
import AssessmentForm from '@/components/seating/assessment-form';
import AssessmentResult from '@/components/seating/assessment-result';
import AssessmentSign from '@/components/seating/assessment-sign';
import { Badge } from '@/components/ui/badge';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { index } from '@/routes/tenant/seating';
import { index as assessments } from '@/routes/tenant/seating/assessments';
import type {
    AssessmentCondition,
    AssessmentFact,
    AssessmentRecord,
    MeasureSuggestions,
} from '@/types';

export default function SeatingAssessment({
    assessment,
    facts,
    conditions,
    suggestions,
    canSign,
    canEdit,
}: {
    assessment: AssessmentRecord;
    facts: AssessmentFact[];
    conditions: AssessmentCondition[];
    suggestions: MeasureSuggestions | null;
    canSign: boolean;
    canEdit: boolean;
}) {
    const tenantSlug = useTenantSlug();
    const { errors } = usePage().props;
    const person = facts.find((fact) => fact.numeral === 'VII')?.value ?? '';
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
                        <Badge
                            variant={
                                assessment.status === 'signed'
                                    ? 'secondary'
                                    : 'outline'
                            }
                        >
                            {assessment.statusLabel}
                        </Badge>
                    }
                    facts={[
                        {
                            icon: UserRound,
                            text:
                                facts.find((fact) => fact.numeral === 'VIII')
                                    ?.value ?? '',
                        },
                    ]}
                />
                {assessment.status === 'signed' && (
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
                {canSign && (
                    <AssessmentSign
                        assessmentId={assessment.id}
                        error={errors['sign']}
                    />
                )}
            </div>
        </>
    );
}
