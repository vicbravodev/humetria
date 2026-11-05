import { Head, Link } from '@inertiajs/react';
import CountTabs from '@/components/humetria/count-tabs';
import SetupFrame from '@/components/setup-frame';
import AppliesNom035 from '@/components/tenancy/applies-nom035';
import AppliesSeating from '@/components/tenancy/applies-seating';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import type { SeatingDeclaration } from '@/hooks/use-seating-applies';
import type { Range } from '@/lib/applicability';
import { dashboard } from '@/routes/tenant';
import { applies, route } from '@/routes/tenant/work-centers';

type Props = {
    workCenter: { id: string; name: string };
    /** The people registered in the center. */
    people: number;
    range: Range;
    norm: 'nom035' | 'seating';
    seating: SeatingDeclaration | null;
};

/** «¿Qué te pide cada norma en este centro?» (board V2-Aplica-Otras): NOM-035 and Ley Silla, one tab each. */
export default function TenancyApplies(props: Props) {
    const tenantSlug = useTenantSlug();
    const tab = (norm: Props['norm']) =>
        applies([tenantSlug, props.workCenter.id], { query: { norm } });

    return (
        <SetupFrame
            caption={`${props.workCenter.name} · configuración inicial`}
            aside={
                <Link
                    href={dashboard(tenantSlug)}
                    className="inline-flex min-h-11 items-center text-sm font-medium text-primary"
                >
                    Hacerlo después
                </Link>
            }
        >
            <Head title={`¿Te aplica? · ${props.workCenter.name}`} />
            <div className="mx-auto flex w-full max-w-[1200px] flex-col gap-7">
                <header className="flex flex-col gap-3.5">
                    <h1 className="text-3xl leading-10 font-semibold tracking-[-0.015em] text-balance">
                        ¿Qué te pide cada norma en este centro?
                    </h1>
                    <CountTabs
                        label="Norma"
                        tabs={[
                            {
                                label: 'NOM-035',
                                href: tab('nom035'),
                                active: props.norm === 'nom035',
                            },
                            {
                                label: 'Ley Silla',
                                href: tab('seating'),
                                active: props.norm === 'seating',
                            },
                        ]}
                    />
                </header>
                {props.norm === 'nom035' ? (
                    <AppliesNom035
                        center={props.workCenter.name}
                        people={props.people}
                        range={props.range}
                        routeHref={route([
                            tenantSlug,
                            props.workCenter.id,
                            'nom035',
                        ])}
                    />
                ) : (
                    <AppliesSeating
                        tenantSlug={tenantSlug}
                        workCenterId={props.workCenter.id}
                        saved={props.seating}
                        routeHref={route([
                            tenantSlug,
                            props.workCenter.id,
                            'seating',
                        ])}
                        panelHref={dashboard(tenantSlug)}
                    />
                )}
            </div>
        </SetupFrame>
    );
}
