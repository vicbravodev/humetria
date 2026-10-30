import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import TraumaForm from '@/components/campaigns/trauma-form';
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

    return (
        <>
            <Head title="Guía I por acontecimiento" />
            <div className="flex max-w-3xl flex-col gap-8 px-4 py-6 md:px-10 md:py-8">
                <Link
                    href={index(tenantSlug)}
                    className="flex w-fit items-center gap-2 text-sm text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft aria-hidden="true" className="size-4" />
                    NOM-035
                </Link>
                <PageHeader
                    caption="NOM-035"
                    title="Guía I por acontecimiento"
                />
                <p className="text-muted-foreground">
                    Cada vez que alguien sufre o presencia un acontecimiento
                    traumático severo durante o con motivo del trabajo, incluido
                    el trayecto directo entre su casa y el trabajo, debe
                    contestar la Guía I para que, si lo necesita, se le canalice
                    a atención (num. 5.5).
                </p>
                <div className="grid max-w-sm gap-2">
                    <Label htmlFor="trauma-work-center">
                        Centro de trabajo
                    </Label>
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
                {workCenter !== null && people !== null && (
                    <TraumaForm
                        // A new list of people for each work center.
                        key={workCenter.value}
                        tenantSlug={tenantSlug}
                        workCenterId={workCenter.value}
                        people={people}
                        search={filters.q}
                        maxPeople={maxPeople}
                        minDeadline={minDeadline}
                        suggestedDeadline={suggestedDeadline}
                    />
                )}
            </div>
        </>
    );
}
