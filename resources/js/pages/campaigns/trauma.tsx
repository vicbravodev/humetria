import { Head, router, setLayoutProps, usePage } from '@inertiajs/react';
import TraumaForm from '@/components/campaigns/trauma-form';
import TraumaNextSteps from '@/components/campaigns/trauma-next-steps';
import Combobox from '@/components/humetria/combobox';
import PageHeader from '@/components/page-header';
import { Label } from '@/components/ui/label';
import { serverSource } from '@/lib/server-options';
import { index } from '@/routes/tenant/campaigns';
import { create } from '@/routes/tenant/campaigns/trauma';
import { options as workCenterOptions } from '@/routes/tenant/work-centers';
import type { ChoiceOption, Paginated, RosterPerson } from '@/types';

export default function Trauma({
    workCenter,
    people,
    filters,
    maxPeople,
    minDeadline,
    suggestedDeadline,
}: {
    workCenter: ChoiceOption | null;
    people: Paginated<RosterPerson> | null;
    filters: { q: string };
    maxPeople: number;
    minDeadline: string;
    suggestedDeadline: string;
}) {
    const tenantSlug = usePage().props.tenant?.slug ?? '';
    setLayoutProps({
        breadcrumbs: [
            { title: 'NOM-035', href: index(tenantSlug) },
            { title: 'Guía I por acontecimiento', href: create(tenantSlug) },
        ],
    });
    const picker = (
        <div className="flex flex-col gap-1.5">
            <Label htmlFor="trauma-work-center">Centro de trabajo</Label>
            <Combobox
                id="trauma-work-center"
                name="work_center"
                source={serverSource(
                    workCenterOptions.url(tenantSlug),
                    workCenter === null ? [] : [workCenter],
                )}
                defaultValue={workCenter ?? undefined}
                placeholder="Elige un centro"
                onChange={(option) =>
                    router.get(
                        create.url(tenantSlug, {
                            query: { work_center: option.value },
                        }),
                    )
                }
            />
        </div>
    );

    return (
        <>
            <Head title="Guía I por acontecimiento" />
            <div className="flex w-full max-w-[1180px] flex-col gap-8 px-4 py-6 md:px-10 md:pt-8 md:pb-16">
                <PageHeader
                    title="Guía I por acontecimiento"
                    description={
                        <span className="inline-block max-w-[720px]">
                            Cuando alguien sufre o presencia un acontecimiento
                            traumático severo durante o con motivo del trabajo,
                            incluido el trayecto directo entre su casa y el
                            trabajo, debe contestar la Guía I para que, si lo
                            necesita, se le canalice a atención (numeral 5.5).
                        </span>
                    }
                />
                <div className="grid grid-cols-[repeat(auto-fit,minmax(min(420px,100%),1fr))] items-start gap-7">
                    <div className="rounded-xl border bg-card p-6">
                        {workCenter !== null && people !== null ? (
                            <TraumaForm
                                // A new list of people for each work center.
                                key={workCenter.value}
                                tenantSlug={tenantSlug}
                                picker={picker}
                                workCenter={workCenter}
                                people={people}
                                search={filters.q}
                                maxPeople={maxPeople}
                                deadline={{
                                    min: minDeadline,
                                    suggested: suggestedDeadline,
                                }}
                            />
                        ) : (
                            <div className="grid gap-4 sm:grid-cols-2">
                                {picker}
                                <p className="self-end text-[13px] leading-[18px] text-muted-foreground sm:pb-3">
                                    Elige dónde pasó para ver a su gente.
                                </p>
                            </div>
                        )}
                    </div>
                    <TraumaNextSteps />
                </div>
            </div>
        </>
    );
}
