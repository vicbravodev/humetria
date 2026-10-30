import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import TraumaForm from '@/components/campaigns/trauma-form';
import PageHeader from '@/components/page-header';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { index } from '@/routes/tenant/campaigns';
import { create } from '@/routes/tenant/campaigns/trauma';
import type { RosterPerson } from '@/types';

export default function Trauma({
    workCenters,
    workCenterId,
    people,
    minDeadline,
    suggestedDeadline,
}: {
    workCenters: { id: string; name: string }[];
    workCenterId: string | null;
    people: RosterPerson[];
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
                    <Select
                        value={workCenterId ?? ''}
                        onValueChange={(id) =>
                            router.get(
                                create.url(tenantSlug, {
                                    query: { work_center: id },
                                }),
                            )
                        }
                    >
                        <SelectTrigger
                            id="trauma-work-center"
                            className="w-full"
                        >
                            <SelectValue placeholder="Elige un centro" />
                        </SelectTrigger>
                        <SelectContent>
                            {workCenters.map((workCenter) => (
                                <SelectItem
                                    key={workCenter.id}
                                    value={workCenter.id}
                                >
                                    {workCenter.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
                {workCenterId !== null && (
                    <TraumaForm
                        // A new list of people for each work center.
                        key={workCenterId}
                        tenantSlug={tenantSlug}
                        workCenterId={workCenterId}
                        people={people}
                        minDeadline={minDeadline}
                        suggestedDeadline={suggestedDeadline}
                    />
                )}
            </div>
        </>
    );
}
