import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { FileText, Factory, Users } from 'lucide-react';
import PageHeader from '@/components/humetria/page-header';
import { Button } from '@/components/ui/button';
import CenterNextStep from '@/components/telework/center-next-step';
import CenterProgressCard from '@/components/telework/center-progress-card';
import CenterRoster from '@/components/telework/center-roster';
import CenterTabs from '@/components/telework/center-tabs';
import ComplianceRows from '@/components/telework/compliance-rows';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { headcountText } from '@/lib/telework/center';
import type {
    CenterProgress,
    CenterTab,
    ComplianceRow,
    CenterNextStepData,
    Paginated,
    CenterPerson,
} from '@/types';
import { teleworkCrumbs } from '@/lib/telework/crumbs';

export default function TeleworkCenter({
    workCenter,
    progress,
    dossierHref,
    teleworkers,
    aspirants,
    rows,
    nextStep,
    tabs,
    people,
}: {
    workCenter: { id: string; name: string; activity: string };
    progress: CenterProgress;
    /** The printable dossier for an inspection; null for the viewer. */
    dossierHref: string | null;
    teleworkers: number;
    aspirants: number;
    rows: ComplianceRow[];
    nextStep: CenterNextStepData | null;
    tabs: CenterTab[];
    /** Null for whoever sees counts only (the viewer). */
    people: Paginated<CenterPerson> | null;
}) {
    const tenantSlug = useTenantSlug();
    setLayoutProps({
        breadcrumbs: teleworkCrumbs(tenantSlug, workCenter, {
            title: 'Personas',
        }),
    });

    return (
        <>
            <Head title={`NOM-037 · ${workCenter.name}`} />
            <div className="flex w-full flex-col gap-8 px-4 py-6 md:px-10 md:py-8">
                <PageHeader
                    title={workCenter.name}
                    facts={[
                        { icon: Factory, text: workCenter.activity },
                        {
                            icon: Users,
                            text: headcountText(teleworkers, aspirants),
                        },
                    ]}
                >
                    {dossierHref !== null && (
                        <Button variant="outline" asChild>
                            <Link href={dossierHref}>
                                <FileText aria-hidden />
                                Expediente
                            </Link>
                        </Button>
                    )}
                </PageHeader>
                {nextStep !== null && <CenterNextStep step={nextStep} />}
                <CenterProgressCard progress={progress} rows={rows} />
                <CenterTabs tabs={tabs} />
                {people === null ? (
                    <p className="text-meta text-muted-foreground">
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
