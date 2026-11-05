import { Head, setLayoutProps } from '@inertiajs/react';
import { Armchair, Factory } from 'lucide-react';
import PageHeader from '@/components/humetria/page-header';
import PlacementResult from '@/components/seating/placement-result';
import PositionWizard from '@/components/seating/position-wizard';
import PositionPeople from '@/components/seating/position-people';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { seatingCrumbs } from '@/lib/seating/crumbs';
import type {
    ConditionOption,
    Paginated,
    PositionPerson,
    StandingPosition,
    TypeOption,
} from '@/types';

export default function SeatingPosition({
    workCenter,
    position,
    types,
    conditions,
    industrial,
    canManage,
    people,
}: {
    workCenter: { id: string; name: string };
    position: StandingPosition | null;
    types: TypeOption[];
    conditions: ConditionOption[];
    industrial: boolean;
    canManage: boolean;
    /** Null for whoever sees counts only (the viewer). */
    people: Paginated<PositionPerson> | null;
}) {
    const tenantSlug = useTenantSlug();
    const title = position?.name ?? 'Nuevo puesto';
    setLayoutProps({
        breadcrumbs: seatingCrumbs(tenantSlug, workCenter, { title }),
    });

    return (
        <>
            <Head title={`${title} · Ley Silla`} />
            <div className="flex w-full max-w-[1240px] flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <PageHeader
                    title={title}
                    facts={[
                        { icon: Factory, text: workCenter.name },
                        ...(position === null
                            ? []
                            : [
                                  {
                                      icon: Armchair,
                                      text: position.figureOneApplies
                                          ? 'Bipedestación prolongada'
                                          : 'Menos de tres horas continuas de pie',
                                  },
                              ]),
                    ]}
                />
                {position !== null && <PlacementResult result={position} />}
                {canManage ? (
                    <div className="card p-6 [&_form_section:first-child]:border-t-0 [&_form_section:first-child]:pt-0">
                        <PositionWizard
                            workCenterId={workCenter.id}
                            position={position}
                            types={types}
                            conditions={conditions}
                            industrial={industrial}
                        />
                    </div>
                ) : (
                    position !== null && (
                        <p className="card p-6 text-muted-foreground">
                            {position.tasks}
                        </p>
                    )
                )}
                {position !== null && people !== null && (
                    <PositionPeople
                        positionId={position.id}
                        people={people}
                        canManage={canManage}
                    />
                )}
            </div>
        </>
    );
}
