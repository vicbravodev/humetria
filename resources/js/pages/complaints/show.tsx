import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import CaseConversation from '@/components/complaints/case-conversation';
import CaseNext from '@/components/complaints/case-next';
import CaseSide from '@/components/complaints/case-side';
import CaseStatus from '@/components/complaints/case-status';
import CaseSteps from '@/components/complaints/case-steps';
import CaseStory from '@/components/complaints/case-story';
import ComplaintsHeader from '@/components/complaints/complaints-header';
import ConfidentialBanner from '@/components/complaints/confidential-banner';
import PrintCaseButton from '@/components/complaints/print-case-button';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { describeDate } from '@/lib/calendar-date';
import { caseLead, receivedLine } from '@/lib/complaints/case';
import { caseSteps } from '@/lib/complaints/deadline';
import { index } from '@/routes/tenant/complaints';
import type { Option } from '@/lib/option-search';
import type {
    CaseCan,
    CaseDeadlines,
    CaseDetail,
    CaseEntryRow,
    CaseResolutionOption,
    Paginated,
} from '@/types';

type Props = {
    case: CaseDetail;
    /** Who has the case, by name. */
    access: string[];
    /** What the person reads: their messages and the answers, the newest first. */
    conversation: Paginated<CaseEntryRow>;
    /** The internal notes: the person never reads them. */
    notes: Paginated<CaseEntryRow>;
    /** What happened to the case. */
    log: Paginated<CaseEntryRow>;
    can: CaseCan;
    candidates: Option[];
    resolutions: CaseResolutionOption[];
    /** The acknowledgement already written, with the day of the answer. */
    acknowledgement: string;
    today: string;
    deadlines: CaseDeadlines;
};

/** «Atender un caso de principio a fin» (board V4-Quejas-Caso): what the person tells, the conversation, the deadlines and the notes. */
export default function Case(props: Props) {
    const {
        case: detail,
        conversation,
        can,
        resolutions,
        acknowledgement,
        deadlines,
    } = props;
    const tenantSlug = useTenantSlug();
    setLayoutProps({
        breadcrumbs: [
            { title: 'Quejas', href: index(tenantSlug) },
            { title: detail.reference },
        ],
    });

    return (
        <>
            <Head title={`Caso ${detail.reference}`} />
            <div className="mx-auto flex w-full max-w-[1240px] flex-col gap-6 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <ConfidentialBanner note="Tu consulta quedó registrada">
                    <strong className="font-semibold">
                        Información confidencial.
                    </strong>{' '}
                    Solo la ven las personas asignadas a este caso. No la
                    compartas fuera de ellas.
                </ConfidentialBanner>
                <ComplaintsHeader
                    eyebrow={detail.typeLabel}
                    title={detail.reference}
                    mono
                    status={
                        <CaseStatus
                            detail={detail}
                            acknowledged={deadlines.acknowledgedOn !== null}
                        />
                    }
                    lead={caseLead(
                        detail,
                        describeDate(detail.receivedOn) ?? detail.receivedOn,
                    )}
                >
                    <PrintCaseButton caseId={detail.id} />
                    <Button variant="ghost" asChild>
                        <Link href={index(tenantSlug)}>
                            <ArrowLeft aria-hidden />
                            Casos
                        </Link>
                    </Button>
                </ComplaintsHeader>
                <CaseSteps
                    steps={caseSteps(
                        detail.status,
                        detail.receivedOn,
                        deadlines,
                    )}
                />
                <div className="grid items-start gap-6 min-[1240px]:grid-cols-[minmax(0,1fr)_360px]">
                    <div className="flex min-w-0 flex-col gap-6">
                        <CaseStory detail={detail} />
                        <CaseConversation
                            messages={conversation}
                            received={receivedLine(detail)}
                        >
                            <CaseNext
                                caseId={detail.id}
                                can={can}
                                draft={acknowledgement}
                                resolutions={resolutions}
                            />
                        </CaseConversation>
                    </div>
                    <CaseSide
                        detail={detail}
                        deadlines={deadlines}
                        today={props.today}
                        notes={props.notes}
                        writable={can.write}
                        candidates={props.candidates}
                        log={props.log}
                        access={props.access}
                    />
                </div>
            </div>
        </>
    );
}
