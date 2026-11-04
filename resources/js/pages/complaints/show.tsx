import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import CaseAccess from '@/components/complaints/case-access';
import CaseActions from '@/components/complaints/case-actions';
import CaseComposer from '@/components/complaints/case-composer';
import CaseThread from '@/components/complaints/case-thread';
import CaseFacts from '@/components/complaints/case-facts';
import CaseIdentity from '@/components/complaints/case-identity';
import CaseStateBadge from '@/components/complaints/case-state-badge';
import CaseWords from '@/components/complaints/case-words';
import ConfidentialBanner from '@/components/complaints/confidential-banner';
import PageHeader from '@/components/humetria/page-header';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { index } from '@/routes/tenant/complaints';
import type { Option } from '@/lib/option-search';
import type {
    CaseCan,
    CaseDetail,
    CaseEntryRow,
    CaseResolutionOption,
    Paginated,
} from '@/types';

export default function Case({
    case: detail,
    access,
    entries,
    can,
    candidates,
    resolutions,
}: {
    case: CaseDetail;
    access: string[];
    entries: Paginated<CaseEntryRow>;
    can: CaseCan;
    candidates: Option[];
    resolutions: CaseResolutionOption[];
}) {
    const tenantSlug = useTenantSlug();

    return (
        <>
            <Head title={`Caso ${detail.reference}`} />
            <div className="flex w-full max-w-[880px] flex-col gap-6 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <ConfidentialBanner />
                <PageHeader
                    caption={detail.typeLabel}
                    title={`Caso ${detail.reference}`}
                    status={
                        <CaseStateBadge
                            status={detail.status}
                            label={detail.resolution ?? detail.statusLabel}
                            overdue={false}
                        />
                    }
                >
                    <Button variant="outline" asChild>
                        <Link href={index.url(tenantSlug)}>
                            <ArrowLeft aria-hidden />
                            Volver a los casos
                        </Link>
                    </Button>
                </PageHeader>
                <CaseActions
                    caseId={detail.id}
                    can={can}
                    onHold={detail.onHold}
                    candidates={candidates}
                    resolutions={resolutions}
                />
                <CaseFacts detail={detail} />
                <CaseWords detail={detail} />
                <CaseIdentity detail={detail} />
                {can.write && <CaseComposer caseId={detail.id} />}
                <CaseThread entries={entries} />
                <CaseAccess names={access} />
            </div>
        </>
    );
}

Case.layout = {
    breadcrumbs: [
        { title: 'NOM-035' },
        { title: 'Quejas y denuncias' },
        { title: 'Caso' },
    ],
};
