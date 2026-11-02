import { Head, setLayoutProps } from '@inertiajs/react';
import { CalendarCheck, Factory } from 'lucide-react';
import PageHeader from '@/components/humetria/page-header';
import CenterLinks from '@/components/seating/center-links';
import PositionsList from '@/components/seating/positions-list';
import SectorForm from '@/components/seating/sector-form';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { index } from '@/routes/tenant/seating';
import type {
    Paginated,
    PositionRow,
    SectorOption,
    SeatingProfile,
} from '@/types';

export default function SeatingCenter({
    workCenter,
    profile,
    sectors,
    canManage,
    canAssess,
    positions,
}: {
    workCenter: { id: string; name: string; activity: string };
    profile: SeatingProfile | null;
    sectors: SectorOption[];
    canManage: boolean;
    canAssess: boolean;
    positions: Paginated<PositionRow>;
}) {
    const tenantSlug = useTenantSlug();
    setLayoutProps({
        breadcrumbs: [
            { title: 'Ley Silla', href: index(tenantSlug) },
            { title: workCenter.name },
        ],
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
                                  { icon: Factory, text: profile.sectorLabel },
                                  {
                                      icon: CalendarCheck,
                                      text: `Declarado el ${profile.declaredOn}`,
                                  },
                              ]),
                    ]}
                >
                    <CenterLinks
                        workCenterId={workCenter.id}
                        canAssess={canAssess}
                    />
                </PageHeader>
                <div className="max-w-[880px] card p-6 [&>form>section:first-child]:border-t-0 [&>form>section:first-child]:pt-0">
                    {canManage ? (
                        <SectorForm
                            workCenterId={workCenter.id}
                            sectors={sectors}
                            profile={profile}
                        />
                    ) : (
                        <p className="text-muted-foreground">
                            {profile === null
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
