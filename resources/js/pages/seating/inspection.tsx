import { Head, setLayoutProps } from '@inertiajs/react';
import InspectionDossier from '@/components/humetria/inspection-dossier';
import InspectionItem from '@/components/humetria/inspection-item';
import InspectionList from '@/components/humetria/inspection-list';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { seatingCrumbs } from '@/lib/seating/crumbs';
import type { InspectionItem as Item } from '@/types';

/** Board V3-Inspeccion for Ley Silla: what an inspector would ask for today in a center, what is missing first, and its dossier. */
export default function SeatingInspection({
    workCenter,
    applicable,
    missing,
    ready,
    dossierHref,
}: {
    workCenter: { id: string; name: string };
    applicable: number;
    missing: Item[];
    ready: Item[];
    dossierHref: string | null;
}) {
    const tenantSlug = useTenantSlug();
    setLayoutProps({
        breadcrumbs: seatingCrumbs(tenantSlug, workCenter, {
            title: 'Inspección',
        }),
    });

    return (
        <>
            <Head title={`Inspección · ${workCenter.name}`} />
            <div className="mx-auto flex w-full max-w-[1180px] flex-col gap-8 px-4 py-6 md:px-12 md:py-10">
                <header className="flex max-w-[760px] flex-col gap-2">
                    <h1 className="text-3xl leading-10 font-semibold tracking-[-0.015em] text-balance">
                        Si hoy llegara una inspección a {workCenter.name}
                    </h1>
                    <p className="text-lg text-muted-foreground">
                        Esto es lo que te pediría el inspector de la Ley Silla,
                        en palabras llanas. Lo que tienes está listo en el
                        expediente; lo que falta tiene su botón para resolverlo.
                    </p>
                </header>
                <div className="grid gap-6 min-[1240px]:grid-cols-[minmax(0,1fr)_360px]">
                    <div className="flex min-w-0 flex-col gap-6">
                        <InspectionList
                            id="missing"
                            title="Te falta"
                            count={missing.length}
                            applicable={applicable}
                            empty="No te falta nada: puedes mostrar cada renglón."
                        >
                            {missing.map((item, index) => (
                                <InspectionItem
                                    key={item.numeral}
                                    item={item}
                                    primary={index === 0}
                                />
                            ))}
                        </InspectionList>
                        <InspectionList
                            id="ready"
                            title="Lo puedes mostrar"
                            count={ready.length}
                            applicable={applicable}
                            empty="Todavía no tienes nada que mostrar. Empieza por lo que te falta."
                        >
                            {ready.map((item) => (
                                <InspectionItem
                                    key={item.numeral}
                                    item={item}
                                    primary={false}
                                />
                            ))}
                        </InspectionList>
                    </div>
                    {dossierHref !== null && (
                        <InspectionDossier href={dossierHref} />
                    )}
                </div>
            </div>
        </>
    );
}
