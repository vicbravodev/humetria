import { Head, Link, setLayoutProps } from '@inertiajs/react';
import { CalendarCheck, Factory } from 'lucide-react';
import PageHeader from '@/components/humetria/page-header';
import CenterTabs from '@/components/seating/center-tabs';
import PositionsList from '@/components/seating/positions-list';
import RequirementsBoard from '@/components/seating/requirements-board';
import SectorForm from '@/components/seating/sector-form';
import { Button } from '@/components/ui/button';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { describeDate } from '@/lib/calendar-date';
import { dossier } from '@/routes/tenant/seating';
import { seatingCrumbs } from '@/lib/seating/crumbs';
import type {
    CenterRequirement,
    Paginated,
    PositionRow,
    RequirementsSummary,
    SectorOption,
    SeatingCenterTab,
    SeatingProfile,
} from '@/types';

export default function SeatingCenter({
    workCenter,
    profile,
    sectors,
    canManage,
    canAssess,
    positions,
    requirements,
    summary,
    tabs,
}: {
    workCenter: { id: string; name: string; activity: string };
    profile: SeatingProfile | null;
    sectors: SectorOption[];
    canManage: boolean;
    canAssess: boolean;
    positions: Paginated<PositionRow>;
    requirements: CenterRequirement[];
    summary: RequirementsSummary;
    tabs: SeatingCenterTab[];
}) {
    const tenantSlug = useTenantSlug();
    setLayoutProps({
        breadcrumbs: seatingCrumbs(tenantSlug, workCenter, {
            title: 'Puestos',
        }),
    });

    return (
        <>
            <Head title={`Ley Silla · ${workCenter.name}`} />
            <div className="flex w-full flex-col gap-8 px-4 py-6 md:px-10 md:py-8">
                <PageHeader
                    title={workCenter.name}
                    facts={[
                        { icon: Factory, text: workCenter.activity },
                        ...(profile === null
                            ? []
                            : [
                                  {
                                      icon: Factory,
                                      text:
                                          profile.sectorLabel ??
                                          'Nadie trabaja de pie',
                                  },
                                  {
                                      icon: CalendarCheck,
                                      text: `Declarado el ${describeDate(profile.declaredOn) ?? profile.declaredOn}`,
                                  },
                              ]),
                    ]}
                >
                    {canAssess && (
                        <Button variant="outline" asChild>
                            <Link href={dossier([tenantSlug, workCenter.id])}>
                                Expediente para inspección
                            </Link>
                        </Button>
                    )}
                </PageHeader>
                <CenterTabs workCenterId={workCenter.id} tabs={tabs} />
                <RequirementsBoard
                    workCenterId={workCenter.id}
                    requirements={requirements}
                    summary={summary}
                />
                <div className="max-w-[880px] card p-6 [&>form>section:first-child]:border-t-0 [&>form>section:first-child]:pt-0">
                    {canManage ? (
                        <SectorForm
                            workCenterId={workCenter.id}
                            sectors={sectors}
                            profile={profile}
                        />
                    ) : (
                        <p className="text-muted-foreground">
                            {profile?.sectorLabel == null
                                ? 'Este centro todavía no declara su sector.'
                                : `Sector declarado: ${profile.sectorLabel}.`}
                        </p>
                    )}
                </div>
                <PositionsList
                    workCenterId={workCenter.id}
                    positions={positions}
                    canManage={canManage}
                />
            </div>
        </>
    );
}
