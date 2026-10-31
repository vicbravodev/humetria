import { Head, setLayoutProps, usePage } from '@inertiajs/react';
import { Factory, Users } from 'lucide-react';
import PageHeader from '@/components/page-header';
import CenterCenterNextStepData from '@/components/telework/center-next-step';
import CenterProgressCard from '@/components/telework/center-progress-card';
import CenterRoster from '@/components/telework/center-roster';
import CenterTabs from '@/components/telework/center-tabs';
import ComplianceRows from '@/components/telework/compliance-rows';
import { headcountText } from '@/lib/telework/center';
import { index } from '@/routes/tenant/telework';
import type {
    CenterProgress,
    CenterTab,
    ComplianceRow,
    CenterNextStepData,
    Paginated,
    CenterPerson,
} from '@/types';

export default function TeleworkCenter({
    workCenter,
    progress,
    teleworkers,
    aspirants,
    rows,
    nextStep,
    tabs,
    people,
}: {
    workCenter: { id: string; name: string; activity: string };
    progress: CenterProgress;
    teleworkers: number;
    aspirants: number;
    rows: ComplianceRow[];
    nextStep: CenterNextStepData | null;
    tabs: CenterTab[];
    /** Null for whoever sees counts only (the viewer). */
    people: Paginated<CenterPerson> | null;
}) {
    const tenantSlug = usePage().props.tenant?.slug ?? '';
    setLayoutProps({
        breadcrumbs: [
            { title: 'NOM-037', href: index(tenantSlug) },
            { title: workCenter.name },
        ],
    });

    return (
        <>
            <Head title={`NOM-037 · ${workCenter.name}`} />
            <div className="flex w-full max-w-[1180px] flex-col gap-8 px-4 py-6 md:px-10 md:py-8">
                <PageHeader
                    title={workCenter.name}
                    facts={[
                        { icon: Factory, text: workCenter.activity },
                        {
                            icon: Users,
                            text: headcountText(teleworkers, aspirants),
                        },
                    ]}
                />
                {nextStep !== null && (
                    <CenterCenterNextStepData step={nextStep} />
                )}
                <CenterProgressCard progress={progress} rows={rows} />
                <CenterTabs tabs={tabs} />
                {people === null ? (
                    <p className="text-[15px] text-muted-foreground">
                        Tu rol ve el avance y el estado de cada renglón, sin los
                        datos de las personas.
                    </p>
                ) : (
                    <CenterRoster people={people} />
                )}
                <ComplianceRows rows={rows} />
            </div>
        </>
    );
}
